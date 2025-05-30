<!-- Firebase Authentication Configuration -->
<!-- Usar la versión más reciente de Firebase SDK -->
<script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-auth-compat.js"></script>

<script>
    // Configuración de Firebase
    const firebaseConfig = {
        apiKey: "AIzaSyD0U1CvTyvvIllUnU3-nF5ENHEIWD9zsrE",
        authDomain: "ckslicensing.firebaseapp.com",
        projectId: "ckslicensing",
    };

    // Variable para controlar si Firebase ya fue inicializado
    let firebaseInitialized = false;
    let authUser = null;

    // Inicializar Firebase con manejo de errores
    function initializeFirebase() {
        try {
            if (!firebaseInitialized) {
                firebase.initializeApp(firebaseConfig);
                firebaseInitialized = true;
                console.log('Firebase inicializado correctamente');
            }
        } catch (error) {
            console.error('Error al inicializar Firebase:', error);
            // Mostrar mensaje de error al usuario
            showError('Error de conexión. Por favor, recarga la página.');
        }
    }

    // Función para mostrar errores
    function showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #f44336;
            color: white;
            padding: 1rem;
            border-radius: 4px;
            z-index: 10000;
        `;
        errorDiv.textContent = message;
        document.body.appendChild(errorDiv);
        
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.parentNode.removeChild(errorDiv);
            }
        }, 5000);
    }

    // Función para verificar autenticación y proteger páginas
    function protectPage() {
        const loadingMessage = document.getElementById('loading-message');
        const mainContent = document.getElementById('main-content');

        // Mostrar loading
        if (loadingMessage) loadingMessage.style.display = 'block';
        if (mainContent) mainContent.style.display = 'none';

        // Inicializar Firebase si no está inicializado
        initializeFirebase();

        // Verificar autenticación
        firebase.auth().onAuthStateChanged((user) => {
            try {
                if (!user) {
                    console.log('Usuario no autenticado, redirigiendo...');
                    // Pequeño delay para evitar redirección inmediata
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 100);
                } else {
                    console.log('Usuario autenticado:', user.email);
                    authUser = user;
                    
                    // Usuario autenticado, mostrar contenido
                    if (mainContent) mainContent.style.display = 'block';
                    if (loadingMessage) loadingMessage.style.display = 'none';

                    // Actualizar elementos de la UI
                    updateUserInterface(user);
                }
            } catch (error) {
                console.error('Error en verificación de autenticación:', error);
                showError('Error de autenticación');
            }
        }, (error) => {
            console.error('Error en onAuthStateChanged:', error);
            showError('Error de conexión con el servidor de autenticación');
        });
    }

    // Función para actualizar elementos de la interfaz
    function updateUserInterface(user) {
        const userEmailElement = document.getElementById('user-email');
        if (userEmailElement) {
            userEmailElement.textContent = user.email;
        }

        // Actualizar otros elementos que muestren info del usuario
        const userNameElement = document.getElementById('user-name');
        if (userNameElement && user.displayName) {
            userNameElement.textContent = user.displayName;
        }
    }

    // Función para verificar si ya está logueado (para index.php)
    function checkIfAlreadyLoggedIn() {
        initializeFirebase();
        
        firebase.auth().onAuthStateChanged((user) => {
            if (user) {
                console.log('Usuario ya autenticado, redirigiendo a create.php');
                setTimeout(() => {
                    window.location.href = 'create.php';
                }, 100);
            }
            // Si no hay usuario en Firebase, permanece en index.php
        }, (error) => {
            console.error('Error al verificar autenticación:', error);
        });
    }

    // Función para cerrar sesión con mejor manejo de errores
    function logout() {
        if (!firebaseInitialized) {
            console.error('Firebase no está inicializado');
            return;
        }

        // Mostrar indicador de carga
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.disabled = true;
            logoutBtn.textContent = 'Cerrando sesión...';
        }

        firebase.auth().signOut().then(() => {
            console.log('Usuario desconectado de Firebase');
            authUser = null;
            
            // Limpiar la sesión PHP si existe
            return fetch('logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
        }).then(() => {
            window.location.href = 'index.php';
        }).catch((error) => {
            console.error('Error al cerrar sesión:', error);
            showError('Error al cerrar sesión');
            
            // Restaurar botón
            if (logoutBtn) {
                logoutBtn.disabled = false;
                logoutBtn.textContent = 'Cerrar Sesión';
            }
            
            // Forzar redirección en caso de error
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1000);
        });
    }

    // Función para verificar si el usuario está autenticado (sin redirección)
    function checkAuth(callback) {
        initializeFirebase();
        
        firebase.auth().onAuthStateChanged((user) => {
            callback(user);
        }, (error) => {
            console.error('Error en checkAuth:', error);
            callback(null);
        });
    }

    // Función para obtener el token del usuario actual
    function getCurrentUserToken() {
        return new Promise((resolve, reject) => {
            if (authUser) {
                authUser.getIdToken().then(resolve).catch(reject);
            } else {
                reject(new Error('No hay usuario autenticado'));
            }
        });
    }

    // Inicializar cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-inicializar Firebase
        initializeFirebase();
    });
</script>

<!-- Estilos mejorados para el mensaje de carga -->
<style>
    #loading-message {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(255, 255, 255, 0.95);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        backdrop-filter: blur(2px);
    }

    .auth-loading-content {
        text-align: center;
        padding: 2rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        max-width: 300px;
    }

    .auth-loading-content h3 {
        margin: 0 0 1rem 0;
        color: #333;
        font-size: 1.2rem;
    }

    .auth-loading-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem auto;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Estilos para mensajes de error */
    .error-message {
        background: #f44336;
        color: white;
        padding: 1rem;
        border-radius: 4px;
        margin: 1rem 0;
        text-align: center;
    }
</style> 
