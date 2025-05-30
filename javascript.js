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

// === FUNCIONES DE AUTENTICACIÓN ===

// Función a llamar después de un login exitoso de Firebase
function onFirebaseLoginSuccess(user) {
  const mensajeEstado = document.getElementById("mensaje-estado");

  if (mensajeEstado) {
    mensajeEstado.textContent = "Login exitoso. Redirigiendo...";
    mensajeEstado.style.color = "green";
  }

  // Crear sesión PHP después del login de Firebase (opcional, si necesitas PHP)
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
        // Sesión PHP creada exitosamente, redirigir
        window.location.href = "create.php";
      } else {
        // Redirigir de todas formas ya que Firebase está autenticado
        window.location.href = "create.php";
      }
    })
    .catch((error) => {
      console.error("Error creando sesión PHP:", error);
      // Redirigir de todas formas ya que Firebase está autenticado
      window.location.href = "create.php";
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

      // Llamar función para crear sesión PHP y redirigir
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
});