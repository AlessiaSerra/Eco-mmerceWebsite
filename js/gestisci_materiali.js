"use strict";



//Funzioni di utilità per mostrare e nascondere messaggi globali nell'applicazione.
// Questi messaggi appaiono in cima e in fondo alla pagina per fornire feedback all'utente.
function displayAppMessage(message, type = 'success') {
    const mainBanner = document.getElementById('topAppFeedback');

    if (!mainBanner) {
        console.error("Banner dei messaggi dell'applicazione non trovati. Controlla gli ID 'topAppFeedback' e 'bottomAppFeedback'.");
        return;
    }

    mainBanner.style.display = 'block';
    mainBanner.classList.remove('success', 'error', 'info', 'warning'); 
    mainBanner.classList.add(type);
    mainBanner.textContent = message;

    window.scrollTo({ top: 0, behavior: 'smooth' });

    setTimeout(() => {
        hideAppMessage();
    }, 6000);
}

function hideAppMessage() {
    const mainBanner = document.getElementById('topAppFeedback');

    if (mainBanner) {
        mainBanner.style.display = 'none';
        mainBanner.textContent = '';
        mainBanner.classList.remove('success', 'error', 'info', 'warning'); // Clean classes
    }
    
}

//Funzioni per la gestione degli errori di validazione specifici per i campi di input.
 
function showInputError(inputElement, errorMessage) {
    if (!inputElement) {
        console.error("Tentativo di mostrare un errore per un elemento di input inesistente.");
        return;
    }

    let errorDisplay = inputElement.closest('.input-field-group')?.querySelector('.field-error-feedback');

    if (!errorDisplay) {
        const parentGroup = inputElement.closest('.input-field-group');
        if (parentGroup) {
            errorDisplay = parentGroup.querySelector('.field-error-feedback');
        }
    }

    if (errorDisplay) {
        errorDisplay.textContent = errorMessage;
        errorDisplay.style.display = 'block';
        inputElement.classList.add('input-invalid');

    } else {
        console.warn(`Impossibile trovare uno span '.field-error-feedback' per l'input ID: ${inputElement.id || 'N/A'}. Ne sto creando uno nuovo.`);
        const newErrorSpan = document.createElement('span');
        newErrorSpan.classList.add('field-error-feedback');
        newErrorSpan.textContent = errorMessage;
        
        const parentGroup = inputElement.closest('.input-field-group');
        if (parentGroup) {
            const label = parentGroup.querySelector('.field-label');
            if (label) {
                parentGroup.insertBefore(newErrorSpan, label);
            } else {
                // Fallback: se non c'è una label, inseriscila
                parentGroup.prepend(newErrorSpan);
            }
        } else {
            // Fallback: se non è stato trovato .input-field-group, inseriscilo
            inputElement.parentNode.insertBefore(newErrorSpan, inputElement);
        }
        inputElement.classList.add('input-invalid');
    }
}

//Funzioni per pulire il banner degli errori di validazione
function clearInputError(inputElement) {
    if (!inputElement) return;

    let errorDisplay = inputElement.closest('.input-field-group')?.querySelector('.field-error-feedback');
    if (!errorDisplay) {
        errorDisplay = inputElement.nextElementSibling;
        if (errorDisplay && !errorDisplay.classList.contains('field-error-feedback')) {
            errorDisplay = null;
        }
    }

    if (errorDisplay) {
        errorDisplay.textContent = '';
        errorDisplay.style.display = 'none';
    }
    inputElement.classList.remove('input-invalid');
}

function clearAllInputErrors() {
    document.querySelectorAll('.field-error-feedback').forEach(span => {
        span.textContent = '';
        span.style.display = 'none';
    });
    document.querySelectorAll('.input-invalid').forEach(element => {
        element.classList.remove('input-invalid');
    });
}

// Funzione di utilità per sanificare i caratteri HTML e prevenire attacchi XSS.
 
function sanitizeHtml(rawText) {
    const textSafe = String(rawText || '');
    const charMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return textSafe.replace(/[&<>"']/g, char => charMap[char]);
}

// Funzione per la formattazione e il parsing delle date.
function convertDateForDisplay(isoDateString) {
    if (!isoDateString) return '';
    try {
        const [year, month, day] = isoDateString.split('-');
        return `${day}/${month}/${year}`;
    } catch (e) {
        console.error("Errore durante la formattazione della data per la visualizzazione:", e);
    return isoDateString;
}
}

function parseDateForInput(displayDateString) {
    if (!displayDateString) return '';
    
    // Se la data è già nel formato YYYY-MM-DD, non convertirla
    if (/^\d{4}-\d{2}-\d{2}$/.test(displayDateString)) {
        return displayDateString;
    }
    // Se è nel formato DD/MM/YYYY, convertila
    const parts = displayDateString.split('/');
    if (parts.length === 3) {
        const day = parts[0].padStart(2, '0');
        const month = parts[1].padStart(2, '0');
        const year = parts[2];
        return `${year}-${month}-${day}`;
    }
    return displayDateString;
}

// Logica principale dell'applicazione per la gestione delle offerte di materiali.


// Listener per il caricamento del DOM
document.addEventListener('DOMContentLoaded', () => {
    // Controlla i messaggi di feedback generati dal PHP e visualizzali
    const phpFeedbackBanner = document.querySelector('.feedback-message');
    if (phpFeedbackBanner && phpFeedbackBanner.textContent.trim() !== '') {
        const messageText = phpFeedbackBanner.textContent.trim();
        const messageType = phpFeedbackBanner.classList.contains('error') || phpFeedbackBanner.classList.contains('errore_critico') ? 'error' :
        phpFeedbackBanner.classList.contains('warning') ? 'warning' : 'info'; // Map more types
        displayAppMessage(messageText, messageType);
        phpFeedbackBanner.style.display = 'none';
        phpFeedbackBanner.textContent = '';
    }

    const addNewOfferForm = document.getElementById('addNewMaterialForm');
    const offersTableBody = document.getElementById('materialsTableBody');
    const noOffersPlaceholder = document.getElementById('noMaterialsPlaceholder');
    const materialsOfferTable = document.getElementById('materialsOfferTable');

    let currentMaterialOffers = [];
    document.querySelectorAll('#materialsTableBody tr').forEach(row => {
        const id = row.dataset.materialEntryId;
        const name = row.querySelector('.material-name-cell .display-mode-text')?.textContent.trim();
        const description = row.querySelector('.material-description-cell .display-mode-text')?.textContent.trim();
        const dateDisplay = row.querySelector('.material-date-cell .display-mode-text')?.textContent.trim();
        
        const rawDate = parseDateForInput(dateDisplay); 
        const quantity = parseInt(row.querySelector('.material-quantity-cell .display-mode-text')?.textContent.trim(), 10);
        const costText = row.querySelector('.material-cost-cell .display-mode-text')?.textContent.trim();
        const cost = parseFloat(costText.replace(',', '.').replace(' €', ''));

        if (id && name && dateDisplay !== undefined && quantity !== undefined && cost !== undefined) {
            currentMaterialOffers.push({
                ID: id,
                NOME: name,
                DESCRIZIONE: description,
                DATA: rawDate, // Formato YYYY-MM-DD
                DATA_VISUALIZZAZIONE: dateDisplay, // Formato DD/MM/YYYY
                QUANTITA: quantity,
                COSTO: cost,
                RAW_COST: parseFloat(cost)
            });
        }
    });

    // Funzione per la visualizzazione della tabella o del messaggio placeholder in base ai dati.
    
    function updateTableVisibility() {
        if (offersTableBody.children.length === 0) {
            materialsOfferTable.style.display = 'none';
            noOffersPlaceholder.style.display = 'block';
        } else {
            materialsOfferTable.style.display = 'table';
            noOffersPlaceholder.style.display = 'none';
        }
    }

    // Gestisce i pulsanti stepper per i campi di input del costo.
    function handleStepperClick() {
    // Trova il contenitore genitore .stepper-control-group
        const parentControlGroup = this.closest('.stepper-control-group, .cost-control-group');
        if (!parentControlGroup) return;

        // Trova l'input all'interno del gruppo. Potrebbe essere .cost-input-field o .quantity-input-field
        const input = parentControlGroup.querySelector('.cost-input-field, .quantity-input-field');
        if (!input) return;

        const step = parseFloat(this.dataset.stepValue);
        let currentValue = parseFloat(input.value.replace(',', '.')); // Gestisce la virgola per l'input value
        if (isNaN(currentValue)) currentValue = 0;

        let newValue = currentValue + step;

        
        if (newValue < 0) { // Il valore non scende sotto zero
            newValue = 0;
        }

        // Determina il numero di cifre decimali in base all'input specifico
        // Se l'input è .cost-input-field, usa 2 decimali. Altrimenti, se è quantity, non usare decimali.
        if (input.classList.contains('cost-input-field')) {
            input.value = newValue.toFixed(2); // Formatta a 2 cifre decimali per il costo
        } else {
            input.value = Math.round(newValue); // Arrotonda al numero intero più vicino per la quantità
        }
    }

// Funzione per inizializzare o re-inizializzare tutti gli event listener degli stepper
    function initializeStepperListeners() {
        document.querySelectorAll('.stepper-btn').forEach(button => {
            // Rimuovi eventuali listener preesistenti per evitare duplicazioni
            button.removeEventListener('click', handleStepperClick);
            // Aggiungi il nuovo listener
            button.addEventListener('click', handleStepperClick);
        });
    }

    // Chiamata iniziale per gli stepper presenti al caricamento della pagina
    initializeStepperListeners();


    // Gestione dell'invio del modulo per aggiungere un nuovo materiale.
    
    if (addNewOfferForm) {
        addNewOfferForm.addEventListener('submit', function(event) {
            clearAllInputErrors();
            let isValid = true;

            const materialNameInput = document.getElementById('newMaterialName');
            const materialDescriptionInput = document.getElementById('newMaterialDescription');
            const materialDateInput = document.getElementById('newMaterialDate');
            const materialQuantityInput = document.getElementById('newMaterialQuantity');
            const materialCostInput = document.getElementById('newMaterialCost');

            // validazione client-side
            if (materialNameInput.value.length < 10 || materialNameInput.value.length > 40 || !/^[A-Za-z0-9\s]*$/.test(materialNameInput.value)) {
                showInputError(materialNameInput, "Il nome deve contenere 10-40 caratteri alfanumerici.");
                isValid = false;
            }
            if (materialDescriptionInput.value.length > 250) {
                showInputError(materialDescriptionInput, "La descrizione non può superare i 250 caratteri.");
                isValid = false;
            }
            if (!materialDateInput.value) {
                showInputError(materialDateInput, "La data di pubblicazione è obbligatoria.");
                isValid = false;
            } else {
                const selectedDate = new Date(materialDateInput.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                if (selectedDate > today) {
                    showInputError(materialDateInput, "La data non può essere futura.");
                    isValid = false;
                }
            }
            const quantity = parseInt(materialQuantityInput.value, 10);
            if (quantity < 0 || isNaN(quantity)) {
                showInputError(materialQuantityInput, "La quantità deve essere un numero non negativo.");
                isValid = false;
            }
            const cost = parseFloat(materialCostInput.value.replace(',', '.')); 
            // Verifica se il costo è un numero valido e non negativo
            if (isNaN(cost) || cost < 0) {
                showInputError(materialCostInput, "Costo: numero non negativo."); 
                isValid = false;
            } else {
                //Cerco di evitare problemi di arrotondamento
                const costInCents = Math.round(cost * 100);

                // Controlla se i centesimi sono multipli di 5
                if (costInCents % 5 !== 0) {
                    showInputError(materialCostInput, "Costo: centesimi multipli di 5 (es: 5.25, 10.50)."); 
                    isValid = false;
                }
            }

            if (!isValid) {
                event.preventDefault();
                displayAppMessage("Si prega di correggere gli errori nel modulo.", "error");
            } else {
                // Se il form ha passato la validazione con successo, può essere mandato al server
                const hiddenActionInput = document.createElement('input');
                hiddenActionInput.type = 'hidden';
                hiddenActionInput.name = 'action_type'; 
                hiddenActionInput.value = 'add'; 
                this.appendChild(hiddenActionInput);
            }
        });

        // Listener per il pulsante "Reset Filtro"
        document.getElementById('clearNewMaterialFormBtn')?.addEventListener('click', () => {
            addNewOfferForm.reset();
            clearAllInputErrors();
        });
    }

    // Funzioni per gestire la modifica della tabella dei materiali disponibili
    let editingRow = null;

    offersTableBody.addEventListener('click', (event) => {
        const targetBtn = event.target.closest('.action-icon-btn');
        if (!targetBtn) return;

        const row = targetBtn.closest('tr');
        if (!row) return;

        const entryId = row.dataset.materialEntryId;

        if (editingRow && editingRow !== row) {
            displayAppMessage("Si prega di salvare o annullare le modifiche correnti prima di modificarne un'altra.", "warning");
            return;
        }

        if (targetBtn.classList.contains('edit-item-btn')) {
            if (editingRow) {
                revertRowChanges(editingRow);
            }
            enterEditMode(row);
            editingRow = row;
        } else if (targetBtn.classList.contains('save-item-btn')) {
            saveRowChanges(row, entryId);
        } else if (targetBtn.classList.contains('cancel-edit-btn')) {
            revertRowChanges(row);
            editingRow = null;
        }
    });

//Funzioni per la "edit mode"
    function enterEditMode(row) {
    // Nascondi il testo in modalità visualizzazione per i campi modificabili
    row.querySelector('.material-description-cell .display-mode-text').style.display = 'none';
    row.querySelector('.material-quantity-cell .display-mode-text').style.display = 'none';
    row.querySelector('.material-cost-cell .display-mode-text').style.display = 'none';

    // Mostra gli input in modalità modifica per i campi modificabili
    row.querySelector('textarea[name="materialDescription"]').style.display = 'block';

    row.querySelector('.material-quantity-cell .stepper-control-group').style.display = 'flex'; 

    row.querySelector('.material-cost-cell .edit-mode-input').style.display = 'flex';

    row.querySelector('.view-actions-container').style.display = 'none';
    row.querySelector('.edit-actions-container').style.display = 'flex';

        // Salvo i valori originari, per ripristinarli in caso di necessità
        row.querySelectorAll('textarea[name="materialDescription"], input[name="materialQuantity"], input[name="materialCost"]').forEach(input => {
            if (input.name === 'materialCost') {
                input.dataset.originalValue = parseFloat(input.value.replace(',', '.')).toFixed(2);
            } else {
                input.dataset.originalValue = input.value;
            }
        });
    }

    function revertRowChanges(row) {
        // Ripristino il valore iniziale dei campi editabili
        row.querySelectorAll('textarea[name="materialDescription"], input[name="materialQuantity"], input[name="materialCost"]').forEach(input => {
            if (input.name === 'materialCost') {
                input.value = parseFloat(input.dataset.originalValue || '0').toFixed(2);
            } else {
                input.value = input.dataset.originalValue || '';
            }
        });


        row.querySelector('textarea[name="materialDescription"]').style.display = 'none';
        row.querySelector('.material-quantity-cell .stepper-control-group').style.display = 'none'; 
        row.querySelector('.material-cost-cell .edit-mode-input').style.display = 'none'; 

        row.querySelectorAll('.display-mode-text').forEach(el => el.style.display = 'block');

        // Aggirorno le quantità ed il costo in base ai valori di input correnti (dopo un potenziale ripristino)
        const quantityInput = row.querySelector('input[name="materialQuantity"]');
        const quantityDisplay = row.querySelector('.material-quantity-cell .display-mode-text');
        if (quantityInput && quantityDisplay) {
            quantityDisplay.textContent = quantityInput.value;
        }

        const costInput = row.querySelector('input[name="materialCost"]');
        const costDisplay = row.querySelector('.material-cost-cell .display-mode-text');
        if (costInput && costDisplay) {
            const currentCost = parseFloat(costInput.value); 
            costDisplay.textContent = `${currentCost.toFixed(2).replace('.', ',')} €`;
        }
        
        row.querySelector('.edit-actions-container').style.display = 'none';
        row.querySelector('.view-actions-container').style.display = 'flex';

        row.querySelectorAll('.field-error-feedback').forEach(span => {
            span.textContent = '';
            span.style.display = 'none';
        });
        row.querySelectorAll('.input-invalid').forEach(input => {
            input.classList.remove('input-invalid');
        });
    }

    //validazioni client-side per la riga
    function validateRow(row) {
        let rowIsValid = true;
        const descInput = row.querySelector(`textarea[name="materialDescription"]`);
        const quantityInput = row.querySelector(`input[name="materialQuantity"]`);
        const costInput = row.querySelector(`input[name="materialCost"]`);

       
        clearInputError(descInput);
        clearInputError(quantityInput);
        clearInputError(costInput);

        if (descInput.value.length > 250) {
            showInputError(descInput, "Descrizione: max 250 caratteri.");
            rowIsValid = false;
        }
        
        const quantity = parseInt(quantityInput.value, 10);
        if (quantity < 0 || isNaN(quantity)) {
            showInputError(quantityInput, "Quantità: numero non negativo.");
            rowIsValid = false;
        }

        const cost = parseFloat(costInput.value.replace(',', '.'));
        if (cost < 0 || isNaN(cost) || Math.round(cost * 100) % 5 !== 0) { 
            showInputError(costInput, "Costo: numero non negativo e centesimi multipli di 5.");
            rowIsValid = false;
        }

        return rowIsValid;
    }

    async function saveRowChanges(row, entryId) {
        // Validazione client-side prima dell'invio al server
        if (!validateRow(row)) {
            displayAppMessage("Si prega di correggere gli errori prima di salvare.", "error");
            return; // Se la validazione fallisce esco dalla funzione
        }

        const formData = new FormData();
        formData.append('action_type', 'update'); // matcha il case 'update' in PHP
        formData.append('item_id', entryId);

        // Prendo i valori dal display mode per i campi non editabili
        const originalName = row.querySelector('.material-name-cell .display-mode-text').textContent;
        const originalDateFormatted = row.querySelector('.material-date-cell .display-mode-text').textContent;

        // Converto la data formattata (DD/MM/YYYY) a YYYY-MM-DD per il backend
        let originalDateRaw = '';
        if (originalDateFormatted) {
            const parts = originalDateFormatted.split('/');
            if (parts.length === 3) {
                originalDateRaw = `${parts[2]}-${parts[1]}-${parts[0]}`;
            }
        }
        
        formData.append('material_name', originalName); // Mando il nome originale, non editabile
        formData.append('material_description', row.querySelector('textarea[name="materialDescription"]').value);
        formData.append('material_date', originalDateRaw); // Manda la data originale, non editabile
        formData.append('material_quantity', row.querySelector('input[name="materialQuantity"]').value);
        formData.append('material_cost', row.querySelector('input[name="materialCost"]').value.replace(',', '.')); 

        try {
            const response = await fetch('material_actions.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                // Aggionra i valori dai dati sanificati ritornati dal PHP
                row.querySelector('.material-name-cell .display-mode-text').textContent = sanitizeHtml(data.updatedData.NOME);
                row.querySelector('.material-description-cell .display-mode-text').textContent = sanitizeHtml(data.updatedData.DESCRIZIONE);
                row.querySelector('.material-date-cell .display-mode-text').textContent = convertDateForDisplay(data.updatedData.DATA);
                row.querySelector('.material-quantity-cell .display-mode-text').textContent = sanitizeHtml(data.updatedData.QUANTITA);
                row.querySelector('.material-cost-cell .display-mode-text').textContent = `${parseFloat(data.updatedData.COSTO).toFixed(2).replace('.', ',')} €`;

                // Aggiona gli attributi cambiati dei campi editabili
                row.querySelector('textarea[name="materialDescription"]').dataset.originalValue = data.updatedData.DESCRIZIONE;
                row.querySelector('input[name="materialQuantity"]').dataset.originalValue = data.updatedData.QUANTITA;
                row.querySelector('input[name="materialCost"]').dataset.originalValue = parseFloat(data.updatedData.COSTO).toFixed(2);

                displayAppMessage(data.message, data.type || 'success');
                revertRowChanges(row); // Ritorno in display mode
                editingRow = null; // Cancellando anche lo stato di editing
            } else {
                displayAppMessage(data.message || "Errore durante il salvataggio delle modifiche.", data.type || 'error');
                if (data.errors) {
                    // mostro i messaggi d'errore per i campi
                    const descInput = row.querySelector(`textarea[name="materialDescription"]`);
                    const quantityInput = row.querySelector(`input[name="materialQuantity"]`);
                    const costInput = row.querySelector(`input[name="materialCost"]`);

                    if (data.errors.material_description) showInputError(descInput, data.errors.material_description);
                    if (data.errors.material_quantity) showInputError(quantityInput, data.errors.material_quantity);
                    if (data.errors.material_cost) showInputError(costInput, data.errors.material_cost);
                }
            }
        } catch (error) {
            console.error('Errore Fetch (saveRowChanges):', error);
            displayAppMessage("Si è verificato un errore di rete o del server durante il salvataggio.", 'error');
            revertRowChanges(row); // Ripristino per network/server error
            editingRow = null;
        }
    }

    // Gestisco la cancellazione (potrebbe esser AJAX ma uso la submission del form per semplicità)
    offersTableBody.addEventListener('submit', (event) => {
        const form = event.target.closest('.inline-form-delete');
        if (!form) return;

        event.preventDefault(); // Prevengo la sottomissione del form, che avviene di default

        // C'è gia una conferma in HRML, ma lo richiedo per ulteriore sicurezza, siccome è un'azione irreversibile
        if (!confirm('Sei sicuro di voler eliminare questo materiale? Questa azione è irreversibile.')) {
            return;
        }

        const formData = new FormData(form); //Prendo i dati dal form
        formData.append('action_type', 'delete'); 

        fetch('material_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayAppMessage(data.message, data.type || 'success');
                const rowToRemove = form.closest('tr');
                if (rowToRemove) {
                    const entryIdToRemove = rowToRemove.dataset.materialEntryId;
                    rowToRemove.remove();
                    currentMaterialOffers = currentMaterialOffers.filter(item => item.ID !== entryIdToRemove);
                    updateTableVisibility(); //Aggiorno dopo i cambiamenti
                }
            } else {
                displayAppMessage(data.message || "Errore durante l'eliminazione del materiale.", data.type || 'error');
            }
        })
        .catch(error => {
            console.error('Errore Fetch (delete):', error);
            displayAppMessage("Si è verificato un errore di rete o del server durante l'eliminazione.", 'error');
        });
    });

    //Funzionalità di filtro per data.
    
    const startDateFilterInput = document.getElementById('startDateFilter');
    const endDateFilterInput = document.getElementById('endDateFilter');
    const applyFilterBtn = document.getElementById('applyDateFilterBtn');
    const clearFilterBtn = document.getElementById('clearDateFilterBtn');

    if (applyFilterBtn && clearFilterBtn) {
        applyFilterBtn.addEventListener('click', () => {
            clearAllInputErrors();
            let filterValid = true;

            const startDate = startDateFilterInput.value;
            const endDate = endDateFilterInput.value;

            if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                showInputError(startDateFilterInput, "La data di inizio non può essere successiva alla data di fine.");
                filterValid = false;
            }

            if (filterValid) {
                filterTableByDate(startDate, endDate);
            } else {
                displayAppMessage("Si prega di correggere gli errori nei filtri.", "error");
            }
        });

        clearFilterBtn.addEventListener('click', () => {
            startDateFilterInput.value = '';
            endDateFilterInput.value = '';
            clearAllInputErrors();
            filterTableByDate('', '');
        });
    }

    function filterTableByDate(startDateStr, endDateStr) {
        const startFilterDate = startDateStr ? new Date(startDateStr) : null;
        const endFilterDate = endDateStr ? new Date(endDateStr) : null;

        if (startFilterDate) startFilterDate.setHours(0, 0, 0, 0);
        if (endFilterDate) endFilterDate.setHours(23, 59, 59, 999);

        // Cancello completamente la tabella per ricostruirla
        offersTableBody.innerHTML = '';
        let itemsShown = 0;

        currentMaterialOffers.forEach(item => {
            const itemDate = new Date(item.DATA); 
            itemDate.setHours(0, 0, 0, 0);

            const matchesFilter =
                (!startFilterDate || itemDate >= startFilterDate) &&
                (!endFilterDate || itemDate <= endFilterDate);

            if (matchesFilter) {
                const newRow = document.createElement('tr');
                newRow.dataset.materialEntryId = item.ID;

                // Ricostruisco la riga in HTML
                newRow.innerHTML = `
                    <td class="material-name-cell" data-label="Materiale">
                        <span class="display-mode-text">${sanitizeHtml(item.NOME)}</span>
                        </td>
                    <td class="material-description-cell" data-label="Dettagli">
                        <span class="display-mode-text description-text">${sanitizeHtml(item.DESCRIZIONE)}</span>
                        <textarea class="edit-mode-input description-textarea hidden-mode-element" name="materialDescription" maxlength="250" data-original-value="${sanitizeHtml(item.DESCRIZIONE)}">${sanitizeHtml(item.DESCRIZIONE)}</textarea>
                        <span class="field-error-feedback" id="materialDescription-error-${item.ID}"></span>
                    </td>
                    <td class="material-date-cell" data-label="Data Offerta">
                        <span class="display-mode-text">${sanitizeHtml(item.DATA)}</span>
                        </td>
                     <td class="material-quantity-cell" data-label="Quantità">
                         <span class="display-mode-text">${sanitizeHtml(item.QUANTITA)}</span>
                        <div class="edit-mode-input stepper-control-group hidden-mode-element input-field-group">
                            <button type="button" class="stepper-btn stepper-plus-btn" data-step-value="1">+</button>
                            <input type="number" class="quantity-input-field" name="materialQuantity" id="materialQuantity-${item.ID}" value="${sanitizeHtml(item.QUANTITA)}" min="0" data-original-value="${sanitizeHtml(item.QUANTITA)}">
                            <button type="button" class="stepper-btn stepper-minus-btn" data-step-value="-1">-</button>
                            <span class="field-error-feedback" id="materialQuantity-error-${item.ID}"></span>
                        </div>
                     </td>
                    <td class="material-cost-cell" data-label="Costo Un. (€)">
                        <span class="display-mode-text price-text">${item.COSTO.toFixed(2).replace('.', ',')} &euro;</span>
                        <div class="edit-mode-input cost-control-group hidden-mode-element">
                            <button type="button" class="stepper-btn stepper-plus-btn" data-step-value="0.05">+</button>
                            <input type="number" class="cost-input-field" name="materialCost" value="${item.RAW_COST.toFixed(2)}" min="0" step="0.05" data-original-value="${item.RAW_COST.toFixed(2)}">
                            <button type="button" class="stepper-btn stepper-minus-btn" data-step-value="-0.05">-</button>
                        </div>
                        <span class="field-error-feedback" id="materialCost-error-${item.ID}"></span>
                    </td>
                    <td class="table-actions-cell" data-label="Opzioni">
                        <div class="view-actions-container">
                            <button type="button" class="action-icon-btn edit-item-btn" title="Modifica Articolo">
                                <img src="../img/btn_modifica3.png" alt="Modifica">
                            </button>
                            <form method="post" action="material_actions.php" class="inline-form-delete">
                                <input type="hidden" name="action_type" value="delete">
                                <input type="hidden" name="item_id" value="${item.ID}">
                                <button type="submit" class="action-icon-btn delete-item-btn" title="Rimuovi Articolo" onclick="return confirm('Sei sicuro di voler eliminare questo materiale? Questa azione è irreversibile.');">
                                    <img src="../img/btn_elimina3.png" alt="Rimuovi">
                                </button>
                            </form>
                        </div>
                        <div class="edit-actions-container hidden-mode-element">
                            <button type="button" class="action-icon-btn save-item-btn" title="Salva Modifiche">
                                <img src="../img/btn_salva3.png" alt="Salva">
                            </button>
                            <button type="button" class="action-icon-btn cancel-edit-btn" title="Annulla Modifiche">
                                <img src="../img/btn_annulla.png" alt="Annulla">
                            </button>
                        </div>
                    </td>
                `;
                offersTableBody.appendChild(newRow);
                itemsShown++;
            }
        });
        updateTableVisibility();
        // Riattacco gli event listeners for per i nuovi stepper
        offersTableBody.querySelectorAll('.stepper-btn').forEach(button => {
            button.removeEventListener('click', handleStepperClick); 
            button.addEventListener('click', handleStepperClick);
        });
    }

    // Visibilità iniziale della tabella
    updateTableVisibility();
});