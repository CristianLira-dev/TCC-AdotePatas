<?php
define('ADOTE_PATAS_DISABLE_HTML_INJECTION', true);
require_once __DIR__ . '/app/security.php';
appStartSession();
include_once 'conexao.php';

// 1. Verifica se a biblioteca FPDF existe
if (!file_exists('fpdf/fpdf.php')) {
    die("Erro: Biblioteca FPDF não encontrada. Por favor, baixe em fpdf.org e coloque na pasta 'fpdf'.");
}
date_default_timezone_set('America/Sao_Paulo');
require('fpdf/fpdf.php');

// 2. Segurança: Verifica Login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_tipo'])) {
    die("Acesso negado. Faça login.");
}

$user_id = $_SESSION['user_id'];
$user_tipo = $_SESSION['user_tipo'];

// 3. Recebe o ID da Solicitação
$id_solicitacao = filter_input(INPUT_GET, 'solicitacao_id', FILTER_VALIDATE_INT);

if (!$id_solicitacao) {
    die("ID da solicitação inválido.");
}

try {
    // 4. Busca os dados COMPLETOS (Juntando 4 tabelas!)
    $sql = "SELECT 
                f.*, -- Todas as respostas do formulário
                
                -- Dados do Adotante
                u.nome as adotante_nome, 
                u.email as adotante_email, 
                u.cidade as adotante_cidade, 
                u.estado as adotante_estado,
                u.cpf as adotante_cpf, -- Se tiver no banco
                
                -- Dados do Pet
                p.nome as pet_nome, 
                p.especie as pet_especie,
                p.raca as pet_raca,
                
                -- Dados para Segurança (Quem é o dono?)
                s.id_protetor_usuario_fk, 
                s.id_protetor_ong_fk
                
            FROM solicitacao s
            JOIN formulario_adocao f ON s.id_solicitacao = f.id_solicitacao_fk
            JOIN usuario u ON s.id_usuario = u.id_usuario
            JOIN pet p ON s.id_pet = p.id_pet
            WHERE s.id_solicitacao = :id
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id_solicitacao]);
    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dados) {
        die("Solicitação não encontrada.");
    }

    // 5. Verificação de Segurança CRÍTICA
    // Só o DONO do pet (protetor) pode ver esse PDF com dados sensíveis
    $sou_dono = false;
    
    if ($user_tipo == 'usuario' && $dados['id_protetor_usuario_fk'] == $user_id) {
        $sou_dono = true;
    } elseif ($user_tipo == 'ong' && $dados['id_protetor_ong_fk'] == $user_id) {
        $sou_dono = true;
    }

    if (!$sou_dono) {
        // Se quiser ser muito rigoroso, pode bloquear. 
        // Mas se o adotante quiser ver o PRÓPRIO formulário?
        // Vamos permitir se for o adotante também:
        if ($dados['id_usuario_fk'] == $user_id) {
             // Ok, é o próprio adotante vendo o que ele enviou
        } else {
             die("Acesso restrito aos envolvidos na adoção.");
        }
    }

    // --- GERAÇÃO DO PDF ---

    // Classe estendida para fazer Header e Footer bonitinhos
    class PDF extends FPDF {
        function Header() {
            // Logo (se tiver, coloque o caminho correto)
            if(file_exists('images/global/Logo-AdotePatas.png')) {
                $this->Image('images/global/Logo-AdotePatas.png',10,6,20);
            }
            $this->SetFont('Arial','B',15);
            // Move to the right
            $this->Cell(80);
            // Title
            $this->Cell(30,10,utf8_decode('Ficha de Interesse em Adoção'),0,0,'C');
            // Line break
            $this->Ln(20);
            $this->SetDrawColor(217, 83, 79); // Cor vermelha do site
            $this->SetLineWidth(1);
            $this->Line(10, 28, 200, 28);
            $this->Ln(10);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->SetTextColor(128);
            $this->Cell(0,10,utf8_decode('Gerado pelo sistema Adote Patas - ' . date('d/m/Y H:i')),0,0,'C');
        }
        
        // Função auxiliar para linhas de dados
        function DataRow($label, $value) {
            $this->SetFont('Arial','B',10);
            $this->SetTextColor(50);
            $this->Cell(70, 8, utf8_decode($label), 0, 0);
            
            $this->SetFont('Arial','',10);
            $this->SetTextColor(0);
            // MultiCell para textos longos não quebrarem o layout
            $this->MultiCell(0, 8, utf8_decode($value));
            
            // Linha cinza fina para separar
            $this->SetDrawColor(230);
            $this->SetLineWidth(0.2);
            $this->Line($this->GetX(), $this->GetY(), 200, $this->GetY());
        }
        
        function SectionTitle($title) {
            $this->Ln(0);
            $this->SetFont('Arial','B',12);
            $this->SetTextColor(217, 83, 79); // Vermelho AdotePatas
            $this->Cell(0, 10, utf8_decode(strtoupper($title)), 0, 1, 'L');
            $this->SetDrawColor(217, 83, 79);
            $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 190, $this->GetY());
            $this->Ln(2);
        }
    }

    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // --- SEÇÃO 1: DADOS DA ADOÇÃO ---
    $pdf->SectionTitle("Dados Principais");
    $pdf->DataRow("Pet de Interesse:", $dados['pet_nome'] . " (" . ucfirst($dados['pet_especie']) . ")");
    $pdf->DataRow("Data da Solicitação:", date('d/m/Y H:i', strtotime($dados['data_envio'])));
    $pdf->DataRow("Protocolo:", "#" . str_pad($dados['id_solicitacao_fk'], 6, '0', STR_PAD_LEFT));

    // --- SEÇÃO 2: DADOS DO INTERESSADO ---
    $pdf->SectionTitle("Dados do Interessado");
    $pdf->DataRow("Nome Completo:", $dados['adotante_nome']);
    $pdf->DataRow("Email:", $dados['adotante_email']);
    $pdf->DataRow("Localização:", $dados['adotante_cidade'] . " - " . $dados['adotante_estado']);

    // --- SEÇÃO 3: RESPOSTAS DO FORMULÁRIO ---
    $pdf->SectionTitle("Respostas do Questionário");

    // Mapeamento amigável das perguntas
    $perguntas = [
        'tem_criancas' => 'Há crianças na residência?',
        'todos_apoiam' => 'Todos apoiam a adoção?',
        'tipo_moradia' => 'Tipo de Moradia',
        'pet_sera_presente' => 'O pet será um presente?',
        'presente_responsavel' => 'Se presente, responsável está ciente?',
        'teve_pets' => 'Histórico com pets',
        'autoriza_visita' => 'Autoriza visita domiciliar?',
        'ciente_devolucao' => 'Ciente sobre devolução?',
        'ciente_termo_responsabilidade' => 'Aceita assinar termo de responsabilidade?'
    ];

    foreach ($perguntas as $coluna => $pergunta) {
        $resposta = $dados[$coluna];
        
        // Formatar respostas booleanas ou vazias
        if ($resposta === 'sim') $resposta = "Sim";
        if ($resposta === 'nao') $resposta = "Não";
        if (empty($resposta)) $resposta = "-";
        if($pergunta === "Aceita assinar termo de responsabilidade?"){
            $resposta = "   $resposta"; 
        }

        $pdf->DataRow($pergunta, $resposta);
    }

    // Saída do PDF
    // 'I' = Inline (abre no navegador), 'D' = Download
    $pdf->Output('I', 'Ficha_Adocao_' . $dados['pet_nome'] . '.pdf');

} catch (PDOException $e) {
    error_log('Erro ao gerar PDF da adoção: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível gerar o PDF. Tente novamente.');
}
?>
