// === FUNCIONALIDAD DEL MONSTRUO ===
const monster = document.getElementById("monster");
const inputUsuario = document.getElementById("input-usuario");
const inputClave = document.getElementById("input-clave");
const body = document.querySelector("body");
const anchoMitad = window.innerWidth / 2;
const altoMitad = window.innerHeight / 2;
let seguirPunteroMouse = true;

// Seguimiento del mouse
if (body && monster) {
  body.addEventListener("mousemove", (m) => {
    if (seguirPunteroMouse) {
      if (m.clientX < anchoMitad && m.clientY < altoMitad) {
        monster.src = "img/idle/2.png";
      } else if (m.clientX < anchoMitad && m.clientY > altoMitad) {
        monster.src = "img/idle/3.png";
      } else if (m.clientX > anchoMitad && m.clientY < altoMitad) {
        monster.src = "img/idle/5.png";
      } else {
        monster.src = "img/idle/4.png";
      }
    }
  });
}

// Eventos del campo usuario
if (inputUsuario && monster) {
  inputUsuario.addEventListener("focus", () => {
    seguirPunteroMouse = false;
  });

  inputUsuario.addEventListener("blur", () => {
    seguirPunteroMouse = true;
  });

  inputUsuario.addEventListener("keyup", () => {
    let usuario = inputUsuario.value.length;
    if (usuario >= 0 && usuario <= 5) {
      monster.src = "img/read/1.png";
    } else if (usuario >= 6 && usuario <= 14) {
      monster.src = "img/read/2.png";
    } else if (usuario >= 15 && usuario <= 20) {
      monster.src = "img/read/3.png";
    } else {
      monster.src = "img/read/4.png";
    }
  });
}

// Eventos del campo contraseña
if (inputClave && monster) {
  inputClave.addEventListener("focus", () => {
    seguirPunteroMouse = false;
    let cont = 1;
    const cubrirOjo = setInterval(() => {
      monster.src = "img/cover/" + cont + ".png";
      if (cont < 8) {
        cont++;
      } else {
        clearInterval(cubrirOjo);
      }
    }, 60);
  });

  inputClave.addEventListener("blur", () => {
    seguirPunteroMouse = true;
    let cont = 7;
    const descubrirOjo = setInterval(() => {
      monster.src = "img/cover/" + cont + ".png";
      if (cont > 1) {
        cont--;
      } else {
        clearInterval(descubrirOjo);
      }
    }, 60);
  });
}

// === FUNCIONES DE AUTENTICACIÓN Y LICENCIA ===

// Función para verificar si está logueado y validar licencia (evitar conflicto con auth.php)
function checkAuthAndLicense() {
  // Primero verificar si hay usuario en Firebase
  if (typeof firebase !== "undefined" && firebase.auth) {
    firebase.auth().onAuthStateChanged((user) => {
      if (user) {
        // Usuario logueado en Firebase, ahora verificar licencia en PHP
        checkLicenseStatus();
      }
      // Si no hay usuario en Firebase, permanece en login
    });
  }
}

// Función para verificar el estado de la licencia
function checkLicenseStatus() {
  fetch("check_license_status.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.user_logged_in && data.license_valid) {
        // Usuario logueado con licencia válida
        window.location.href = "create.php";
      } else if (data.user_logged_in && !data.license_valid) {
        // Usuario logueado pero sin licencia válida
        window.location.href = "licencia.php";
      }
      // Si no está logueado en PHP, permanece en index.php
    })
    .catch((error) => {
      console.error("Error verificando estado de licencia:", error);
    });
}

// Función a llamar después de un login exitoso de Firebase
function onFirebaseLoginSuccess(user) {
  const mensajeEstado = document.getElementById("mensaje-estado");

  // Crear sesión PHP después del login de Firebase
  fetch("create_php_session.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      email: user.email,
      uid: user.uid
    })
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Sesión PHP creada, ahora verificar licencia
        if (mensajeEstado) {
          mensajeEstado.textContent = "Verificando licencia...";
          mensajeEstado.style.color = "blue";
        }

        checkLicenseStatus();
      } else {
        if (mensajeEstado) {
          mensajeEstado.textContent = "Error creando sesión";
          mensajeEstado.style.color = "red";
        }
      }
    })
    .catch((error) => {
      console.error("Error creando sesión PHP:", error);
      if (mensajeEstado) {
        mensajeEstado.textContent = "Error de conexión";
        mensajeEstado.style.color = "red";
      }
    });
}

// === FUNCIONALIDAD DE LOGIN ===

// Función para manejar el login
function handleLogin(event) {
  event.preventDefault();

  if (!inputUsuario || !inputClave) {
    console.error("Elementos de input no encontrados");
    return;
  }

  const email = inputUsuario.value;
  const password = inputClave.value;
  const mensajeEstado = document.getElementById("mensaje-estado");

  // Limpiar mensaje anterior
  if (mensajeEstado) {
    mensajeEstado.textContent = "";
    mensajeEstado.style.color = "";
  }

  // Mostrar estado de carga
  if (mensajeEstado) {
    mensajeEstado.textContent = "Iniciando sesión...";
    mensajeEstado.style.color = "blue";
  }

  // Verificar que Firebase esté disponible
  if (typeof firebase === "undefined" || !firebase.auth) {
    if (mensajeEstado) {
      mensajeEstado.textContent = "Error: Firebase no está disponible";
      mensajeEstado.style.color = "red";
    }
    return;
  }

  // Intentar hacer login con Firebase
  firebase
    .auth()
    .signInWithEmailAndPassword(email, password)
    .then((userCredential) => {
      // Login exitoso en Firebase
      const user = userCredential.user;
      console.log("Usuario logueado en Firebase:", user.email);

      // Mostrar mensaje de éxito
      if (mensajeEstado) {
        mensajeEstado.textContent = "Login exitoso. Verificando licencia...";
        mensajeEstado.style.color = "green";
      }

      // Llamar función para crear sesión PHP y verificar licencia
      onFirebaseLoginSuccess(user);
    })
    .catch((error) => {
      // Login fallido
      console.error("Error en login:", error);

      // Mostrar mensaje de error
      let errorMessage = "Error en el login. ";
      switch (error.code) {
        case "auth/user-not-found":
          errorMessage += "Usuario no encontrado.";
          break;
        case "auth/wrong-password":
          errorMessage += "Contraseña incorrecta.";
          break;
        case "auth/invalid-email":
          errorMessage += "Email inválido.";
          break;
        case "auth/user-disabled":
          errorMessage += "Usuario deshabilitado.";
          break;
        case "auth/too-many-requests":
          errorMessage += "Demasiados intentos fallidos. Intenta más tarde.";
          break;
        default:
          errorMessage += "Verifique sus credenciales.";
      }

      if (mensajeEstado) {
        mensajeEstado.textContent = errorMessage;
        mensajeEstado.style.color = "red";
      }

      // Limpiar campos después de error
      setTimeout(() => {
        if (inputUsuario) inputUsuario.value = "";
        if (inputClave) inputClave.value = "";
        if (mensajeEstado) mensajeEstado.textContent = "";
      }, 3000);
    });
}

// Inicialización cuando el DOM esté cargado
document.addEventListener("DOMContentLoaded", function () {
  // Agregar event listener al formulario
  const loginForm = document.getElementById("login-form");
  if (loginForm) {
    loginForm.addEventListener("submit", handleLogin);
  }

  // Nota: checkAuthAndLicense() se llama desde auth.php como checkIfAlreadyLoggedIn()
  // para evitar duplicación de lógica
});
