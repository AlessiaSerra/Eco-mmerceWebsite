"use strict";

// Funzioni per mostrare e nascondere messaggi globali nell'applicazione. Questi messaggi appaiono in cima
//  e in fondo alla pagina per fornire feedback all'utente.
function displayAppMessage(message, type = 'success') {
    const mainBanner = document.getElementById('topAppFeedback');
    const footerBanner = document.getElementById('bottomAppFeedback'); // Assicurati che esista nel footer

    if (!mainBanner) {
        console.error("Banner principale dei messaggi dell'applicazione non trovato. Controlla l'ID 'topAppFeedback'.");
        return;
    }

    mainBanner.style.display = 'block';
    mainBanner.classList.remove('success', 'error', 'info', 'warning');
    mainBanner.classList.add(type);
    mainBanner.textContent = message;
    mainBanner.setAttribute('role', (type === 'error' || type === 'warning') ? 'alert' : 'status');
    mainBanner.setAttribute('aria-live', (type === 'error' || type === 'warning') ? 'assertive' : 'polite');

    if (footerBanner) {
        footerBanner.style.display = 'block';
        footerBanner.classList.remove('success', 'error', 'info', 'warning');
        footerBanner.classList.add(type);
        footerBanner.textContent = message;
        footerBanner.setAttribute('role', (type === 'error' || type === 'warning') ? 'alert' : 'status');
        footerBanner.setAttribute('aria-live', (type === 'error' || type === 'warning') ? 'assertive' : 'polite');
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });

    setTimeout(() => {
        hideAppMessage();
    }, 6000);
}

function hideAppMessage() {
    const mainBanner = document.getElementById('topAppFeedback');
    const footerBanner = document.getElementById('bottomAppFeedback');

    if (mainBanner) {
        mainBanner.style.display = 'none';
        mainBanner.textContent = '';
        mainBanner.classList.remove('success', 'error', 'info', 'warning'); // Pulisci le classi
    }
    if (footerBanner) {
        footerBanner.style.display = 'none';
        footerBanner.textContent = '';
        footerBanner.classList.remove('success', 'error', 'info', 'warning'); // Pulisci le classi
    }
}

// Funzioni per la gestione degli errori di validazione specifici per i campi di input.
 // Adattate per posizionare l'errore sopra la label/input per i filtri data.

function showInputError(inputElement, errorMessage) {
    if (!inputElement) {
        console.error("Tentativo di mostrare un errore per un elemento di input inesistente.");
        return;
    }

    const inputGroup = inputElement.closest('.input-field-group');
    if (!inputGroup) {
        console.error("L'input element non è all'interno di un .input-field-group:", inputElement);
        return;
    }

    const errorDisplay = inputGroup.querySelector('.field-error-feedback');

    if (errorDisplay) {
        errorDisplay.textContent = errorMessage;
        errorDisplay.style.display = 'block';
        inputElement.classList.add('input-invalid');
        inputElement.setAttribute('aria-invalid', 'true');
        inputElement.setAttribute('aria-describedby', errorDisplay.id || inputElement.id + '-error');
    } else {
        console.error("Nessun elemento .field-error-feedback trovato per l'input:", inputElement);
    }
}

function clearInputError(inputElement) {
    if (!inputElement) return;

    const inputGroup = inputElement.closest('.input-field-group');
    if (!inputGroup) return;

    const errorDisplay = inputGroup.querySelector('.field-error-feedback');

    if (errorDisplay) {
        errorDisplay.textContent = '';
        errorDisplay.style.display = 'none';
    }
    inputElement.classList.remove('input-invalid');
    inputElement.removeAttribute('aria-invalid');
}

function clearAllInputErrors() {
    document.querySelectorAll('.field-error-feedback').forEach(span => {
        span.textContent = '';
        span.style.display = 'none';
    });
    document.querySelectorAll('.input-invalid').forEach(element => {
        element.classList.remove('input-invalid');
        element.removeAttribute('aria-invalid');
    });
}

// Per verificare che la data sia valida (YYYY-MM-DD)
function isValidDate(dateString) {
    if (!dateString) return true; //Per rendere questa funzione generica, considero una data vuota come valida
    const regEx = /^\d{4}-\d{2}-\d{2}$/;
    if (!dateString.match(regEx)) return false; // formato non valido
    const d = new Date(dateString);
    const dNum = d.getTime();
    if (!dNum && dNum !== 0) return false; // NaN -> invalido
    return d.toISOString().slice(0, 10) === dateString;
}


document.addEventListener('DOMContentLoaded', () => {
    const dateFilterForm = document.getElementById('dateFilterForm');
    const startDateFilterInput = document.getElementById('startDateFilter');
    const endDateFilterInput = document.getElementById('endDateFilter');

    // Variabili per visualizzare gli errori generati dal PHP
    if (phpStartDateError) {
        showInputError(startDateFilterInput, phpStartDateError);
    }
    if (phpEndDateError) {
        showInputError(endDateFilterInput, phpEndDateError);
    }

    // Listener per il submit di "Applica Filtri"
    if (dateFilterForm) {
        dateFilterForm.addEventListener('submit', function(event) {
            event.preventDefault(); // In un primo momento, blocca il submit del form

            clearAllInputErrors(); // Cancella gli errori precedenti

            const startDate = startDateFilterInput.value;
            const endDate = endDateFilterInput.value;
            let isValid = true;

            // Valido la data di inizio
            if (startDate && !isValidDate(startDate)) {
                showInputError(startDateFilterInput, "Formato data di inizio non valido (YYYY-MM-DD).");
                isValid = false;
            }

            // Valido la data di fine
            if (endDate && !isValidDate(endDate)) {
                showInputError(endDateFilterInput, "Formato data di fine non valido (YYYY-MM-DD).");
                isValid = false;
            }

            // Se entrambe le date sono presenti e valide, controllo la consistenza
            if (isValid && startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);
                if (start > end) {
                    showInputError(endDateFilterInput, "La data di fine non può essere precedente alla data di inizio.");
                    isValid = false;
                }
            }

            if (isValid) {
                // Se la validazione passa, faccio il submit del form (con una GET)
                // La pagina verrà automaticamente aggiornata
                this.submit();
            } else {
                displayAppMessage("Si prega di correggere gli errori nelle date del filtro.", "error");
            }
        });
    }

    // Cancello gli errori di validazione data quando un utente la cambia
    startDateFilterInput.addEventListener('input', () => clearInputError(startDateFilterInput));
    endDateFilterInput.addEventListener('input', () => clearInputError(endDateFilterInput));

    // Gestisco lo stato iniziale del notificationBanner 
    const notificationBanner = document.getElementById('notificationBanner');
    if (notificationBanner && notificationBanner.textContent.trim() !== '') {
        // Se c'è già un messaggio da PHP, mostralo
        notificationBanner.style.display = 'block';
    } else if (notificationBanner) {
        notificationBanner.style.display = 'none'; // Se non c'è, nascondi
    }
});