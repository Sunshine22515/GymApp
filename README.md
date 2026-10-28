# REP•LOG — fichas de treino

App web responsiva para um administrador preparar uma ficha individual para cada aluno. O aluno entra com a própria conta, consulta a ficha e regista séries, repetições e peso.

## Tecnologias

- HTML e PHP geram as páginas.
- CSS adapta o aspeto a computador e celular.
- JavaScript confirma ações no painel e regista o service worker.
- PHP valida as contas, os papéis e as operações.
- MySQL guarda utilizadores, fichas, exercícios e registos.

## Executar localmente com XAMPP

1. Instala o XAMPP e inicia **Apache** e **MySQL**.
2. Copia esta pasta para `C:\\xampp\\htdocs\\gym-app`.
3. Abre `http://localhost/phpmyadmin`, cria uma base de dados chamada `gym_app` e seleciona-a.
4. Abre o separador **Importar**, escolhe `database.sql` e executa. O ficheiro cria as tabelas dentro da base selecionada.
5. Copia `config.local.php.example` para `config.local.php`. Edita `config.local.php` e coloca os dados MySQL locais. Em XAMPP, em geral, host `127.0.0.1`, base `gym_app`, utilizador `root` e senha vazia.
6. Abre `http://localhost/gym-app/setup.php` e cria a primeira conta de administrador.
7. Usa o endereço `http://localhost/gym-app/` para iniciar sessão.

## Passos de uso

1. O administrador cria uma conta por aluno no painel.
2. Seleciona um aluno na lista e adiciona os dias de treino e exercícios à ficha.
3. O aluno entra com o nome de utilizador e senha criados pelo administrador.
4. Na área do aluno, regista o número da série, repetições concluídas e peso usado.

## Pontos importantes do código

- **`database.sql` — relações entre dados:** `users` identifica cada pessoa e o seu papel. `workout_plans.student_id` associa uma ficha a um único aluno. `plan_exercises` contém os exercícios dessa ficha. `training_logs` guarda o desempenho do aluno. As chaves estrangeiras apagam os dados dependentes quando uma conta ou exercício é removido.
- **`auth.php` — sessão, papéis e CSRF:** `requireRole('admin')` e `requireRole('student')` protegem cada área. O token CSRF impede que outro site envie formulários como se fosse a pessoa autenticada.
- **`admin.php` — gestão por aluno:** o painel carrega alunos e a ficha selecionada. Ao editar ou apagar exercícios, a consulta verifica também o ID da ficha; isso evita alterar a ficha de outra pessoa apenas trocando um parâmetro no endereço.
- **`aluno.php` — privacidade da ficha:** o ID do aluno vem da sessão, não de um campo enviado pelo navegador. Antes de guardar uma série, o servidor confirma que o exercício pertence à ficha desse aluno.
- **Senhas:** `password_hash()` guarda apenas um resumo da senha. `password_verify()` valida a senha no início de sessão. A senha original nunca é guardada na base.
- **`config.local.php` — credenciais:** contém as credenciais privadas da base e está listado em `.gitignore`. Não publiques esse ficheiro nem o envies a outras pessoas.
- **`assets/styles.css` — celular:** a grelha muda para uma coluna em ecrãs pequenos e os campos/botões mantêm tamanho confortável para toque.
- **`sw.js` e `manifest.webmanifest` — instalação:** permitem instalar a app pelo navegador em endereço HTTPS. O service worker guarda apenas CSS, JavaScript, ícone e manifesto; páginas privadas e dados precisam de ligação ao servidor.

## Administrador inicial

`setup.php` só cria a conta de administrador quando a tabela `users` está vazia. Depois da primeira conta, a página deixa de permitir novos administradores. Para voltar a configurar do zero, apaga os dados da base de dados e importa o esquema outra vez.
