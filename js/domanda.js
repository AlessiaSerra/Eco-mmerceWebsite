"use strict";

// Funzioni per mostrare e nascondere messaggi globali nell'applicazione. Questi messaggi appaiono in cima
//  e in fondo alla pagina per fornire feedback all'utente.

function displayAppMessage(message, type='success'){
    const mainBanner = document.getElementById('topAppFeedback');
    const footerBanner = document.getElementById('bottomAppFeedback');

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
        mainBanner.classList.remove('success', 'error', 'info', 'warning');
    }
    if (footerBanner) {
        footerBanner.style.display = 'none';
        footerBanner.textContent = '';
        footerBanner.classList.remove('success', 'error', 'info', 'warning'); 
    }
}

// Funzioni per la gestione degli errori di validazione specifici per i campi di input.
 // Adattate per posizionare l'errore sopra la label/input per i filtri data.

function showInputError(inputElement, errorMessage) {
    if (!inputElement) {
        console.error("Tentativo di mostrare un errore per un elemento di input inesistente.");
        return;
    }

    let errorDisplay;
    const inputGroup = inputElement.closest('.input-field-group'); 

    if (inputGroup) { // Filtrare gli input data
        errorDisplay = inputGroup.querySelector('.field-error-feedback');
    } else { // Per quantità
        errorDisplay = inputElement.nextElementSibling;
        if (!errorDisplay || !errorDisplay.classList.contains('field-error-feedback')) {
            // Fallback: se manca uno span apposito, lo crep
            console.warn("Could not find direct error span for quantity input. Attempting to create one.");
            errorDisplay = document.createElement('span');
            errorDisplay.classList.add('field-error-feedback');
            inputElement.parentNode.insertBefore(errorDisplay, inputElement.nextSibling); 
        }
    }

    if (errorDisplay) {
        errorDisplay.textContent = errorMessage;
        errorDisplay.style.display = 'block';
        inputElement.classList.add('input-invalid');
        inputElement.setAttribute('aria-invalid', 'true'); // Per accessibilità
        inputElement.setAttribute('aria-describedby', errorDisplay.id || inputElement.id + '-error');
    } else {
        console.error("Failed to find or create error display for input:", inputElement);
    }
}


function clearInputError(inputElement) {
    if (!inputElement) return;

    let errorDisplay;
    const inputGroup = inputElement.closest('.input-field-group');

    if (inputGroup) {
        errorDisplay = inputGroup.querySelector('.field-error-feedback');
    } else {
        errorDisplay = inputElement.nextElementSibling;
        if (!errorDisplay || !errorDisplay.classList.contains('field-error-feedback')) {
            // Controllo se errorDisplay è stato creato dinamicamente o manca
            errorDisplay = null; // Reset a null se non è stato trovato come figlio
        }
    }

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


// LOGICA PRINCIPALE PER L'ACQUISTO DEL MATERIALE
document.addEventListener('DOMContentLoaded', () => {

    if (typeof artisanCredit === 'undefined' || isNaN(artisanCredit)) {
        console.error("Errore critico: 'artisanCredit' non è stato definito correttamente dal PHP.");
        displayAppMessage("Si è verificato un problema nel caricamento dei dati del credito. Riprova più tardi.", "error");
        return; // Se non c'è credito interrompo l'esecuzione dello script
    }

    const quantityInputs = document.querySelectorAll('.quantity-input');
    const totalCostDisplay = document.getElementById('totalCostDisplay');
    const confirmPurchaseBtn = document.getElementById('confirmPurchaseBtn');
    const cancelSelectionBtn = document.getElementById('cancelSelectionBtn');
    const dateFilterForm = document.getElementById('dateFilterForm'); // Prendo tutto il form
    const startDateFilterInput = document.getElementById('startDateFilter');
    const endDateFilterInput = document.getElementById('endDateFilter');
    const applyDateFilterBtn = document.getElementById('applyDateFilterBtn');

    // Variabili per visualizzare gli errori generati dal PHP
    if (phpStartDateError) {
        showInputError(startDateFilterInput, phpStartDateError);
    }
    if (phpEndDateError) {
        showInputError(endDateFilterInput, phpEndDateError);
    }

    // Per tenere traccia dinamicamente delle quantità
    let currentSelectedMaterials = {};
    quantityInputs.forEach(input => {
        const materialId = parseInt(input.dataset.materialId, 10);
        const quantity = parseInt(input.value, 10);
        if (!isNaN(materialId) && !isNaN(quantity) && quantity > 0) {
            currentSelectedMaterials[materialId] = quantity;
        }
    });

    // Calcola e aggiorna il costo totale mostrato e aggiorna lo stato del bottone per procedere all'acquisto
    function updateTotalCost() {
        let totalCost = 0;

        quantityInputs.forEach(input => {
            const materialId = input.dataset.materialId;
            const unitCost = parseFloat(input.dataset.unitCost);
            let quantity = parseInt(input.value, 10);

            // Validazione per quantità
            if (isNaN(quantity) || quantity < 0) {
                quantity = 0; // Default a 0 se invalido
                input.value = 0; // Resetto input
                showInputError(input, "Quantità non valida. Deve essere un numero intero non negativo.");
            } else if (quantity > parseInt(input.max, 10)) {
                quantity = parseInt(input.max, 10); // Il massimo è la quantità disponibile
                input.value = parseInt(input.max, 10); 
                showInputError(input, `Quantità massima disponibile: ${parseInt(input.max, 10)}`);
            } else {
                clearInputError(input); // Cancello gli errori se è invalido
            }

            // Aggiorno l'oggetto currentSelectedMaterials 
            if (quantity > 0) {
                currentSelectedMaterials[materialId] = quantity;
            } else {
                delete currentSelectedMaterials[materialId]; // Rimuovo se la quantità è nulla
            }

            // Aggiorno il costo parziale di ogni item
            const partialCostSpan = document.querySelector(`.partial-cost[data-material-id="${materialId}"]`);
            if (partialCostSpan) {
                const currentPartialCost = isNaN(quantity) ? 0 : quantity * unitCost;
                partialCostSpan.textContent = `€${currentPartialCost.toFixed(2).replace('.', ',')}`;
            }
        });

        // Calcolo il costo tot da currentSelectedMaterials per garantire la correttezza
        let actualTotalCost = 0;
        for (const id in currentSelectedMaterials) {
            const input = document.querySelector(`.quantity-input[data-material-id="${id}"]`);
            if (input) {
                actualTotalCost += currentSelectedMaterials[id] * parseFloat(input.dataset.unitCost);
            }
        }
        totalCostDisplay.textContent = `€${actualTotalCost.toFixed(2).replace('.', ',')}`;

        // Aggiorno lo stato del bottone acquista
        togglePurchaseButton(actualTotalCost);
    }

    // Se si supera il credito dell'artigiano, disabilito il bottone per procedere all'acquisto
    function togglePurchaseButton(currentCalculatedTotalCost) {
        if (currentCalculatedTotalCost <= artisanCredit) {
            confirmPurchaseBtn.disabled = false;
            confirmPurchaseBtn.setAttribute('aria-disabled', 'false');
        } else {
            confirmPurchaseBtn.disabled = true;
            confirmPurchaseBtn.setAttribute('aria-disabled', 'true');
            if (currentCalculatedTotalCost > 0) { // Mostro il warning se è stato selezionato un item che non mi posso più permettere
                displayAppMessage(`Il costo totale (€${currentCalculatedTotalCost.toFixed(2).replace('.', ',')}) supera il tuo credito disponibile (€${artisanCredit.toFixed(2).replace('.', ',')}).`, 'error');
            }
        }
    }

    // Listener per il submit di "Applica Filtri"
    if (dateFilterForm) {
        dateFilterForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Blocca l'invio del form al server

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
                // Se la validazione passa, faccio il submit del form
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

    // Salvo i materiali e le quantità correnti nellasessione PHP  via AJAX.
    
    function saveSelectedItemsTemporarily() {
        const selectedMaterials = {};
        // Popola selectedMaterials dallo stato corrente con le quantità in input
        quantityInputs.forEach(input => {
            const materialId = input.dataset.materialId;
            const quantity = parseInt(input.value);
            if (!isNaN(quantity) && quantity > 0) {
                selectedMaterials[materialId] = quantity;
            }
        });

        fetch('domanda.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=save_selected_items&selectedMaterialsData=${encodeURIComponent(JSON.stringify(selectedMaterials))}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'error') {
                displayAppMessage(data.message || 'Errore durante il salvataggio temporaneo.', 'error');
            }
            // console.log('Server response from temporary save:', data);
        })
        .catch(error => {
            console.error('Error saving selected items temporarily:', error);
            displayAppMessage('Errore di comunicazione con il server per il salvataggio temporaneo.', 'error');
        });
    }

    // Aggiungo degli event listener per gli input di quantità
    quantityInputs.forEach(input => {
        input.addEventListener('input', () => {
            updateTotalCost();
            saveSelectedItemsTemporarily();
        });
        input.addEventListener('change', () => { // Ascolta anche il change
            updateTotalCost();
            saveSelectedItemsTemporarily();
        });
    });

    // Event listener per "Annulla Tutti" 
    cancelSelectionBtn.addEventListener('click', () => {
        quantityInputs.forEach(input => {
            input.value = 0; // Reset input
            clearInputError(input); // Cancello ogni errore associato
        });
        currentSelectedMaterials = {}; 
        updateTotalCost(); // Chiamerà anche togglePurchaseButton
        saveSelectedItemsTemporarily(); // Cancello il "carrello" temporaneo
    });

    // Calcolo inziale al caricamento della pagina, in caso di valori pre-compilati
    updateTotalCost();
});