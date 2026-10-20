"use strict";


function dataValida(data) {
  const regGiorno = /^(0?[1-9]|[12][0-9]|3[01])$/;
  const regMese = /^(0?[1-9]|1[012])$/;
  const regAnno = /^\d{4}$/;

  const dataArr = data.split("-");
  const annoStr = dataArr[0];
  const meseStr = dataArr[1];
  const giornoStr = dataArr[2];
  
  if (!regGiorno.test(giornoStr)) {
    window.alert("Il giorno non è stato introdotto correttamente, usare formato aaaa-mm-gg");
    return false;
  } else if (!regMese.test(meseStr)) {
    window.alert("Il mese non è stato introdotto correttamente, usare formato aaaa-mm-gg");
    return false;
  } else if (!regAnno.test(annoStr)) {
    window.alert("L'anno non è stato introdotto correttamente, usare formato aaaa-mm-gg");
    return false;
} 
     // Se il formato è corretto secondo regDataFormato, procedo all'analisi per il confronto con la data odierna.
    const anno = parseInt(annoStr, 10);
    const mese = parseInt(meseStr, 10);
    const giorno = parseInt(giornoStr, 10);

    // Creazione di un oggetto Date per la data inserita
    // Il mese in JavaScript è 0-indicizzato (0 per Gennaio, 11 per Dicembre)
    const dataInserita = new Date(anno, mese - 1, giorno);

    // Verifica se la data creata corrisponde ai valori originali
    if (dataInserita.getFullYear() !== anno || 
        dataInserita.getMonth() !== (mese - 1) || 
        dataInserita.getDate() !== giorno) {
        window.alert("La data inserita non esiste (es. 30 febbraio)");
    return false;
        } 

    // Ottengo la data odierna (solo giorno, mese, anno per confronto)
    const oggi = new Date();
    oggi.setHours(0, 0, 0, 0); // Azzera ore, minuti, secondi, millisecondi per confronto solo sulla data

    // Confronto con la data odierna
    // Non accettare date nel futuro: la data inserita deve essere minore o uguale a oggi.
    if (dataInserita > oggi) { 
        window.alert("Non è possibile inserire una data appartenente al futuro. Si prega di selezionare una data odierna o passata.");
        return false;
    }

    return true;
}


function validateRegistraArtigiano(nome, cognome, dataNasc, credito, indirizzo, username, password) {

    const regNome = /^[\p{L}\s]{4,14}$/u; 
    const regCognome = /^[\p{L}\s']{4,16}$/u;
    const regDataNasc = /(^((19|[2-9][0-9])\d\d[-]((0[1-9]|1[0-2])[-](0[1-9]|1[0-9]|2[0-8])|((0[13578]|1[02])[-](29|30|31))|((0[469]|11)[-](29|30))))$)|(^((19|[2-9][0-9])(00|04|08|12|16|20|24|28|32|36|40|44|48|52|56|60|64|68|72|76|80|84|88|92|96))[-]02[-]29$)/;
    const regIndirizzo = /^(Via|Corso)\s+([\p{L}\d\s\.'\-]+)\s+(\d{1,3}[a-zA-Z]?)[ ,]+\s*([\p{L}\s]+)$/u;

    const regCredito = /^\d+(\.(00|05|10|15|20|25|30|35|40|45|50|55|60|65|70|75|80|85|90|95))?$/;
    const regUsername = /^\p{L}[\p{L}\p{N}_-]*$/u;
    const regPassword = /^(?=.*\p{Lu})(?=.*\p{Ll})(?=.*\p{N})(?=.*[.;+=])[\p{L}\p{N}\p{P}\p{S}]{8,16}$/u;


    let campiMancanti = "";

    if (nome === "") campiMancanti += "nome\n";
    else if (!regNome.test(nome)) {
        window.alert("Formato nome non valido. Deve contenere minimo 4, massimo 14 caratteri (tra lettere e spazi).");
        return false;
    }

    if (cognome === "") campiMancanti += "cognome\n";
    else if (!regCognome.test(cognome)) {
        window.alert("Formato cognome non valido. Deve contenere minimo 4, massimo 16 caratteri (tra lettere, spazi e apostrofo).");
        return false;
    }

    if(dataNasc == "")
            campiMancanti += "data di nascita\n";
    else if(!regDataNasc.test(dataNasc) && dataNasc != ""){
            window.alert("Formato data di nascita non valido. Deve essere nella forma yyyy-mm-aa. Il primo 0 per il mese e il giorno può essere omesso.");
            return false;
        }
    else if (!dataValida(dataNasc)) {
        window.alert("Inserisci valori consistenti per i campi giorno, mese e un anno. ");
            return false;
    }

    if(indirizzo == "")
            campiMancanti += "indirizzo\n";
        if(!regIndirizzo.test(indirizzo) && indirizzo != ""){
            window.alert("Formato indirizzo non valido. Deve essere nella forma 'Via/Corso Nome NumeroCivico, Città'. Il nome può contenere solo caratteri alfabetici e il numero civico deve essere un numero naturale composto da massimo 3 cifre.");
            return false;
        }
        


    if(credito == "")
            campiMancanti += "credito\n";
        if(!regCredito.test(credito) && credito != ""){
            window.alert("Formato credito non valido. Come seconda cifra decimale dei centesimi sono validi solo 0 e 5.");
            return false;
        }


    if (username === "") campiMancanti += "username\n";
    else if (!regUsername.test(username)) {
        window.alert("Formato username non valido. Deve contenere minimo 4 e massimo 10 caratteri, tra lettere numeri o i caratteri '-' e '_'.");
        return false;
    }

     
    if (password === "") campiMancanti += "password\n";
    else if (password.length < 8 || password.length > 16) {
            window.alert("La password deve essere lunga da 8 a 16 caratteri.");
            return false; }
    else if (!regPassword.test(password)) {
        window.alert("Password non valida. Deve contenere minimo 8 e massimo 16 caratteri, deve contenere almeno 1 lettera maiuscola, 1 lettera minuscola, 1 numero e 1 caratteri speciali tra i seguenti .;+=");
        return false;
    }


    if (campiMancanti !== "") {
        window.alert("Non hai inserito i seguenti campi:\n" + campiMancanti);
        return false;
    }

    return true; 
}


function validateRegistraAzienda(ragione, indirizzo, username, password){
    const regRagione = /^\p{Lu}[\p{L}\p{N} &']{0,29}$/u;
    const regInd = /^(Via|Corso)\s+([\p{L}\d\s\.'\-]+)\s+(\d{1,3}[a-zA-Z]?)[ ,]+\s*([\p{L}\s]+)$/u;
    const regUsername = /^\p{L}[\p{L}\p{N}_-]*$/u;
    const regPassword = /^(?=.*\p{Lu})(?=.*\p{Ll})(?=.*\p{N})(?=.*[.;+=])[\p{L}\p{N}\p{P}\p{S}]{8,16}$/u;

    let campiMancanti = "";

    
    if (ragione === "") campiMancanti += "nome\n";
      else if (ragione.length > 30) {
        window.alert(ragioneError, "La ragione sociale non può superare i 30 caratteri.");
    } else if (!regRagione.test(ragione)) {
        window.alert("Formato ragione non valido. Può contenere massimo 30 caratteri, con lettere, numeri ed i caratteri & e spazio come caratteri accettabili. Deve iniziare con una lettera maiuscola.");
        return false;
    }

    if(indirizzo == "")
            campiMancanti += "indirizzo\n";
        if(!regInd.test(indirizzo)){
            window.alert("Formato indirizzo non valido. Deve essere nella forma 'Via/Corso Nome NumeroCivico, Città'. Il nome può contenere solo caratteri alfabetici e il numero civico deve essere un numero naturale composto da massimo 3 cifre.");
            return false;
        }


    if (username === "") campiMancanti += "username\n";
    
    else if (username.length < 4 || username.length > 10) {
            window.alert("L'username deve essere lungo dai 4 ai 10 caratteri.");
            return false;
        } 
    else if (!regUsername.test(username)) {
        window.alert("Formato username non valido. Deve contenere minimo 4 e massimo 10 caratteri, tra lettere numeri o i caratteri '-' e '_'.");
        return false;
    }

    
    if (password === "") campiMancanti += "password\n";
    else if (password.length < 8 || password.length > 16) {
            window.alert("La password deve essere lunga da 8 a 16 caratteri.");
            return false; }
    else if (!regPassword.test(password)) {
        window.alert("Password non valida. Deve contenere minimo 8 e massimo 16 caratteri, deve contenere almeno 1 lettera maiuscola, 1 lettera minuscola, 1 numero e 1 carattere speciale tra i seguenti .;+=");
        return false;
    }


    if (campiMancanti !== "") {
        window.alert("Non hai inserito i seguenti campi:\n" + campiMancanti);
        return false;
    }

    return true;
}


window.addEventListener("DOMContentLoaded", function () {   
    const form_azienda = document.getElementById("registra_azienda")
    const form_artigiano = document.getElementById("registra_artigiano")

    form_artigiano.addEventListener("submit", function (event) {

        // Campi per "Registrazione Artigiano/Designer"
        const nome = document.getElementById("name").value.trim();
        const cognome = document.getElementById("surname").value.trim();
        const dataNasc = document.getElementById("birthdate").value.trim();
        const credito = document.getElementById("credit").value.trim();
        const indirizzo = document.getElementById("address_art").value.trim();
        const username = document.getElementById("nick_art").value.trim(); 
        const password = document.getElementById("password_art").value.trim(); 
    
        
        if (!validateRegistraArtigiano(nome, cognome, dataNasc, credito, indirizzo, username, password)) {
            event.preventDefault();     // blocca invio al server
            return
        }
    })

    form_azienda.addEventListener("submit", function (event) {
        // Campi per "Registrazione Azienda"
        const ragione = document.getElementById("ragione_az").value.trim();
        const indirizzo = document.getElementById("address2_az").value.trim();
        const username = document.getElementById("nick_az").value.trim(); 
        const password = document.getElementById("password_az").value.trim(); 

        if (!validateRegistraAzienda(ragione, indirizzo, username, password)) {
            event.preventDefault();     // blocca invio al server
            return
        }

    })
})