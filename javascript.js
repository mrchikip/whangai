// === FUNCIONALIDAD DEL MONSTRUO ===
const monster = document.getElementById("monster");
const inputUsuario = document.getElementById("input-usuario");
const inputClave = document.getElementById("input-clave");
const body = document.querySelector("body");
const anchoMitad = window.innerWidth / 2;
const altoMitad = window.innerHeight / 2;
let seguirPunteroMouse = true;

// Seguimiento del mouse
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

// Eventos del campo usuario
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

// Eventos del campo contraseña
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

// === FUNCIONALIDAD DE LOGIN ===

// Función para manejar el login
function handleLogin(event) {
  event.preventDefault();

  const email = inputUsuario.value;
  const password = inputClave.value;
  const mensajeEstado = document.getElementById("mensaje-estado");

  // Limpiar mensaje anterior
  mensajeEstado.textContent = "";
  mensajeEstado.style.color = "";

  // Mostrar estado de carga
  mensajeEstado.textContent = "Iniciando sesión...";
  mensajeEstado.style.color = "blue";

  // Intentar hacer login con Firebase
  firebase
    .auth()
    .signInWithEmailAndPassword(email, password)
    .then((userCredential) => {
      // Login exitoso
      const user = userCredential.user;
      console.log("Usuario logueado:", user.email);

      // Mostrar mensaje de éxito
      mensajeEstado.textContent = "Login exitoso. Redirigiendo...";
      mensajeEstado.style.color = "green";

      // Redirigir a create.php después de un breve delay
      setTimeout(() => {
        window.location.href = "create.php";
      }, 1500);
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

      mensajeEstado.textContent = errorMessage;
      mensajeEstado.style.color = "red";

      // Limpiar campos después de error
      setTimeout(() => {
        inputUsuario.value = "";
        inputClave.value = "";
        mensajeEstado.textContent = "";
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
});
