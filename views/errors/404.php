<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>(function(){try{var s=localStorage.getItem("stoque_tema");var t=(s==="light"||s==="dark")?s:(window.matchMedia("(prefers-color-scheme: light)").matches?"light":"dark");document.documentElement.setAttribute("data-theme",t);document.documentElement.setAttribute("data-bs-theme",t);}catch(e){}})();</script>
    <title>Página Não Encontrada (404) - StoqueOnline</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&family=Plus+Jakarta+Sans:wght@400;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 text-center p-4">
    <div class="card border-0 shadow-lg rounded-4 p-5 max-w-lg">
        <div class="display-1 fw-extrabold text-primary mb-2">404</div>
        <h3 class="fw-bold text-dark mb-3">Página Não Encontrada</h3>
        <p class="text-muted mb-4">O link que você tentou acessar não existe ou foi movido para outro endereço.</p>
        <div>
            <a href="<?= url() ?>" class="btn btn-primary btn-lg px-4 fw-bold">
                <i class="fa-solid fa-house me-2"></i> Voltar à Página Inicial
            </a>
        </div>
    </div>
</body>
</html>
