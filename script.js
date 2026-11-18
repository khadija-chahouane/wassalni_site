const container = document.getElementById('container');

const registerBtn = document.getElementById('register');
const loginBtn = document.getElementById('login');

registerBtn.addEventListener('click', () => {
    container.classList.add('active');
});

loginBtn.addEventListener('click', () => {
    container.classList.remove('active');
});

const splash = document.getElementById("splashScreen");

splash.addEventListener("click", function () {

    splash.classList.add("hide");

    setTimeout(function () {
        splash.remove();
    }, 700);

});

