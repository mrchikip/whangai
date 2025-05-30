<?php
// includes/auth.php - Solo lógica de autenticación
?>
<!-- Firebase Authentication Configuration -->
<!-- Agrega el SDK principal de Firebase -->
<script src="https://www.gstatic.com/firebasejs/9.10.0/firebase-app-compat.js"></script>
<!-- Agrega el SDK de Authentication -->
<script src="https://www.gstatic.com/firebasejs/9.10.0/firebase-auth-compat.js"></script>

<script>
// Configuración de Firebase
const firebaseConfig = {
    apiKey: "AIzaSyD0U1CvTyvvIllUnU3-nF5ENHEIWD9zsrE",
    authDomain: "ckslicensing.firebaseapp.com",
    projectId: "ckslicensing",
};

// Inicializa Firebase
firebase.initializeApp(firebaseConfig);

// Función para verificar autenticación y proteger páginas
function protectPage() {
    const loadingMessage = document.getElementById('loading-message');
    const mainContent = document.getElementById('main-content');

    if (loadingMessage) loadingMessage.style.display = 'block';
    if (mainContent) mainContent.style.display = 'none';

    firebase.auth().onAuthStateChanged((user) => {
        if (!user) {
            console.log('Usuario no autenticado, redirigiendo...');
            window.location.href = 'index.php';
        } else {
            console.log('Usuario autenticado:', user.email);
            // Verificar también la licencia cuando protege páginas
            checkLicenseForProtectedPage();
        }
    });
}

// Nueva función para verificar licencia en páginas protegidas (create.php)
function checkLicenseForProtectedPage() {
    fetch('check_license_status.php')
        .then(response => response.json())
        .then(data => {
            if (!data.license_valid) {
                // Licencia no válida, redirigir a licencia.php
                console.log('Licencia no válida, redirigiendo a licencia.php');
                window.location.href = 'licencia.php';
            } else {
                // Licencia válida, mostrar contenido
                const loadingMessage = document.getElementById('loading-message');
                const mainContent = document.getElementById('main-content');

                if (mainContent) mainContent.style.display = 'block';
                if (loadingMessage) loadingMessage.style.display = 'none';

                const userEmailElement = document.getElementById('user-email');
                if (userEmailElement) {
                    userEmailElement.textContent = firebase.auth().currentUser.email;
                }
            }
        })
        .catch(error => {
            console.error('Error verificando licencia:', error);
            // En caso de error, redirigir a licencia.php por seguridad
            window.location.href = 'licencia.php';
        });
}

// Función para verificar si ya está logueado (para index.php)
function checkIfAlreadyLoggedIn() {
    firebase.auth().onAuthStateChanged((user) => {
        if (user) {
            console.log('Usuario ya autenticado, verificando licencia...');
            // Verificar licencia antes de redirigir
            fetch('check_license_status.php')
                .then(response => response.json())
                .then(data => {
                    if (data.user_logged_in && data.license_valid) {
                        // Usuario logueado con licencia válida -> create.php
                        window.location.href = 'create.php';
                    } else if (data.user_logged_in && !data.license_valid) {
                        // Usuario logueado pero sin licencia válida -> licencia.php
                        window.location.href = 'licencia.php';
                    }
                    // Si no está logueado en PHP, permanece en index.php
                })
                .catch(error => {
                    console.error('Error verificando licencia:', error);
                    // En caso de error, redirigir a licencia.php por seguridad
                    window.location.href = 'licencia.php';
                });
        }
        // Si no hay usuario en Firebase, permanece en index.php
    });
}

// Función para cerrar sesión
function logout() {
    firebase.auth().signOut().then(() => {
        console.log('Usuario desconectado');
        // También limpiar la sesión PHP
        fetch('logout.php', {
                method: 'POST'
            })
            .then(() => {
                window.location.href = 'index.php';
            })
            .catch(() => {
                window.location.href = 'index.php';
            });
    }).catch((error) => {
        console.error('Error al cerrar sesión:', error);
    });
}

// Función para verificar si el usuario está autenticado (sin redirección)
function checkAuth(callback) {
    firebase.auth().onAuthStateChanged((user) => {
        callback(user);
    });
}
</script>

<!-- Estilos para el mensaje de carga -->
<style>
#loading-message {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.auth-loading-content {
    text-align: center;
    padding: 2rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
</style>