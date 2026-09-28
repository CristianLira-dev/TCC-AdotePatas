<?php
require_once __DIR__ . '/app/security.php';
appStartSession();
include_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

// --- 1. Validação e Segurança ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_tipo'])) {
    http_response_code(403);
    echo '<div class="col-12 text-center"><p>Não autorizado. Faça login para filtrar.</p></div>';
    exit;
}

$user_id = $_SESSION['user_id'];
$base_path = ($_SERVER['SERVER_NAME'] == 'localhost') ? '/TCC-AdotePatas/AdotePatas/' : '/';

try {
    // --- 2. Buscar Favoritos do Usuário (Como estava) ---
    $favoritos_usuario = [];
    $sql_fav = "SELECT id_pet FROM favorito WHERE id_usuario = :id_usuario";
    $stmt_fav = $conn->prepare($sql_fav);
    $stmt_fav->execute([':id_usuario' => $user_id]);
    $favoritos_usuario = $stmt_fav->fetchAll(PDO::FETCH_COLUMN, 0);
    $favoritos_usuario = array_map('intval', $favoritos_usuario);

    // --- 3. Receber os dados via POST ---
    $filtros_json = $_POST['filtros'] ?? '[]';
    $filtros = json_decode($filtros_json, true);
    $pesquisa = isset($_POST['pesquisa']) ? trim($_POST['pesquisa']) : '';

    // --- 4. Construir a Query Base ---
    $sql = "SELECT DISTINCT
                p.id_pet, p.nome, p.sexo,
                pf.caminho_foto AS foto
            FROM 
                pet AS p
            LEFT JOIN (
                SELECT id_pet_fk, MIN(id_foto) as min_id_foto
                FROM pet_fotos
                GROUP BY id_pet_fk
            ) pf_min ON p.id_pet = pf_min.id_pet_fk
            LEFT JOIN 
                pet_fotos AS pf ON pf.id_foto = pf_min.min_id_foto
            WHERE 
                p.status_disponibilidade = 'disponivel'";
    
    $params = [];
    // $conditions irá guardar as cláusulas principais (que usam AND)
    $conditions = []; 

    // --- 5. Aplicar Pesquisa por Nome (Sempre AND) ---
    if (!empty($pesquisa)) {
        $conditions[] = "p.nome LIKE :pesquisa";
        $params[':pesquisa'] = '%' . $pesquisa . '%';
    }

    // --- 6. Aplicar Filtros de Características (LÓGICA 'OR') ---
    
    // Este array $filtro_conditions guardará APENAS as características
    $filtro_conditions = []; 
    
    if (!empty($filtros) && is_array($filtros)) {
        foreach ($filtros as $index => $filtro) {
            $paramName = ":filtro_" . $index;
            
            // Adiciona a condição ao array de filtros (que usará OR)
            // Usa LOWER() para ser case-insensitive ("docil" == "Dócil")
            //
            $filtro_conditions[] = "LOWER(p.caracteristicas) LIKE " . $paramName;
            
            // Prepara o valor do parâmetro
            $params[$paramName] = '%"' . strtolower($filtro) . '"%';
        }
    }

    // --- 7. Juntar as Condições ---
    
    // Se houver UMA OU MAIS condições de filtro de características...
    if (!empty($filtro_conditions)) {
        // ...junta todas elas com 'OR' e agrupa entre parênteses.
        // Isto vira -> (condicao1 OR condicao2 OR condicao3)
        $conditions[] = "(" . implode(" OR ", $filtro_conditions) . ")";
    }

    // --- 8. Adicionar todas as condições (AND) à query ---
    // Agora, junta a pesquisa de nome (se houver) E o bloco de filtros (se houver)
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY p.id_pet DESC LIMIT 100";
    
    // --- 9. Executar e Construir o HTML ---
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = '';
    
    if (empty($pets)) {
        // Vazio. O JS em 'pets-adocao.php' já tem a lógica
        // para mostrar a animação 'gato-deitado'.
    } else {
        foreach ($pets as $pet) {
            $is_favorito = in_array($pet['id_pet'], $favoritos_usuario);
            $foto_path = !empty($pet['foto']) ? htmlspecialchars($pet['foto']) : 'images/placeholder-pet.png';
            $pet_nome = htmlspecialchars($pet['nome']);
            $alt_text = "Foto de " . $pet_nome;

            $html .= '<div class="col">';
            $html .= '  <a href="' . $base_path . 'pet-detalhe/' . $pet['id_pet'] . '" class="pet-card-link">';
            $html .= '    <div class="pet-card">';
            $html .= '      <div class="pet-card-img"><img src="' . $foto_path . '" alt="' . $alt_text . '"></div>';
            $html .= '      <div class="pet-card-body">';
            $html .= '        <h2 class="pet-name">' . $pet_nome . '</h2>';
            
            if (!empty($pet['sexo'])) {
                $html .= ($pet['sexo'] == 'femea') 
                    ? '<i class="fa-solid fa-venus pet-gender-female" aria-label="Fêmea" title="Fêmea"></i>' 
                    : '<i class="fa-solid fa-mars pet-gender-male" aria-label="Macho" title="Macho"></i>';
            }
            
            $like_class = $is_favorito ? 'fa-solid fa-heart favorited' : 'fa-regular fa-heart';
            $html .= '        <i class="pet-like ' . $like_class . '" 
                                data-pet-id="' . $pet['id_pet'] . '" 
                                aria-label="Favoritar" 
                                role="button"></i>';
            $html .= '      </div>'; // .pet-card-body
            $html .= '    </div>'; // .pet-card
            $html .= '  </a>'; // .pet-card-link
            $html .= '</div>'; // .col
        }
    }
    
    // Retorna o HTML final para o JavaScript
    echo $html;
    
} catch (PDOException $e) {
    // Se a query falhar, loga o erro REAL no servidor e avisa o JS
    error_log("ERRO em filtrar-pets.php (Lógica OR): " . $e->getMessage() . " --- SQL: " . $sql . " --- Params: " . json_encode($params));
    http_response_code(500);
    // Esta resposta aciona o .catch() no seu JavaScript
    echo '<div class="col-12 text-center"><p>Erro ao filtrar pets. Tente novamente.</p></div>';
}
?>
