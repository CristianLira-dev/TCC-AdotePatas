        document.addEventListener('DOMContentLoaded',()=>{
            const adoptBtn = document.getElementById('adoptBtn');
            const pawPrints = document.getElementById('pawPrints');
            const counterNumber = document.getElementById('counterNumber');
            let count = 0;

            if(!pawPrints){
                return;
            }
             
            // Criar patinhas aleatórias ao redor do botão
            (() => {
                pawPrints.innerHTML = '';
                
                for (let i = 0; i < 20; i++) {
                    const paw = document.createElement('div');
                    paw.classList.add('paw');
                    paw.innerHTML = '🐾';
                    paw.style.left = `${Math.random() * 35}%`;
                    paw.style.top = `${Math.random() * 35}%`;
                    paw.style.animationDelay = `${Math.random() * 5}s`;
                    pawPrints.appendChild(paw);
                }
            })();
        });