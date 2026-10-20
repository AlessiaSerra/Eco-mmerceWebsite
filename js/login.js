/*Se l'utente non si può registrare quando username e password non rispettano determinati
criteri, allora non esiste un utente che inserisce valori non accettati dal form di registrazione.
Per questo, applico lo stesso criterio di validazione per username e password sia per 
il form di registrazione, sia per quello di login.*/

/*!!! in registrazione dovrei accettare solo .;+= come caratteri speciali, ma nella validazione di login permetto altri caratteri speciali oltre questi,
altrimenti non mi farebbe entrare nei profili già definiti nel database, siccome non rispettano le stesse specifiche richieste in registrazione */

"use strict";

//validazione client side del form di login
function validateLogin(username, password) {
    const regUsername = /^([A-Za-z])[A-Za-z\d\-_]{3,9}$/;
    const regPassword = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^\da-zA-Z]).{8,16}$/;
    let corr = true; 

    let campiMancanti = "";

    if (username === "") campiMancanti += "username\n";
    else if (!regUsername.test(username)) {
        window.alert("Autenticazione fallita: \nUsername non valido.");
        corr = false;
    }

    if (password === "") campiMancanti += "password\n";
    else if (!regPassword.test(password)) {
        window.alert("Autenticazione fallita:\nPassword non valida.");
        corr = false;
    }

    if (campiMancanti !== "") {
        window.alert("Non hai inserito i seguenti campi:\n" + campiMancanti);
        corr = false;
    }

    if (!corr)
        return false;
        
    return true;
}

window.addEventListener("DOMContentLoaded", function () {   
    const form = document.getElementById("login")

    form.addEventListener("submit", function (event) {
        const username = document.getElementById("user").value.trim()   
        const password = document.getElementById("pwd").value.trim()  
        if (!validateLogin(username, password)) {
            event.preventDefault();     // blocca invio al server
            return
        }
    })
})


