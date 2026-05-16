<?php
header('Content-Type: text/html; charset=UTF-8');
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/login.php';
?>

<?php view('header', ['title' => 'Login']) ?>

<div class="d-flex align-items-center justify-content-center" style="min-height: 75vh;">
    
    <div class="card shadow-lg border-0 rounded-4" style="width: 100%; max-width: 400px;">
        <div class="card-body p-5">
            
            <div class="text-center mb-4">
                <h2 class="fw-bold" style="color: #0d6efd">Iniciar Sesión</h2>
                <p class="text-muted small">Ingresa tus credenciales para acceder</p>
            </div>

            <?php if (isset($errors['login'])) : ?>
                <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <?= htmlspecialchars($errors['login']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif ?>

            <form action="login.php" method="post">
                <div class="mb-4">
                    <label for="username" class="form-label fw-bold text-secondary small">Nombre de Usuario:</label>
                    <input 
                        type="text" 
                        name="username" 
                        id="username" 
                        class="form-control form-control-lg bg-light <?= isset($errors['username']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($inputs['username'] ?? '') ?>" 
                        placeholder="Ej: Pedro Pascal"
                    >
                    <?php if (isset($errors['username'])): ?>
                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['username']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg fw-bold">Ingresar</button>
                </div>
            </form>

        </div>
    </div>
</div>

<?php view('footer') ?>