# Eco-mmerce

Eco-mmerce is a web platform designed to promote the circular economy by connecting companies with scrap materials to artisans and designers looking for sustainable and affordable raw materials. 

This document outlines the project organization, code architecture, and main design choices.

## Project Structure
The project adopts a modular approach and is organized into folders divided by language, in order to promote code maintainability and readability:

*   **/php**: Contains all server-side logic and views.
    *   **Frontend:** Main pages such as `home.php`, `offerta.php`, `conferma.php`, `login.php`, `registrazione.php`, `lista.php`, and `guida.php`.
    *   **Backend:** Scripts dedicated to data processing and SQL queries, such as `registra_azienda_back.php`, `registra_artigiano_back.php`, `material_actions.php`, and `login_back.php`.
    *   **Shared components:** `header.php` and `footer.php` to ensure consistent navigation. All pages dynamically manage sessions and authorize access, redirecting unauthenticated users where necessary.
*   **/js**: Contains client-side scripts (`domanda.js`, `lista.js`, `login.js`, `registra.js`, `gestisci_materiali.js`) for interactive data validation and dynamic interface management.
*   **/css**: Contains the global stylesheet (`stile.css`) used for the entire application.
*   **/img**: Contains the images and icons used in the interface.

## Data Validation and Security
Data security and integrity are managed on three successive levels:

1.  **HTML5:** Use of native attributes (`required`, `pattern`, `min`, `max`) for preventive blocking on the client-side.
2.  **JavaScript:** Advanced validation via regular expressions (RegEx) before sending data to the server, providing immediate and precise feedback to the user.
3.  **PHP (Backend):** The final and fundamental layer of security to prevent tampering. It utilizes:
    *   **Prepared Statements** to prevent SQL Injection attacks.
    *   `htmlspecialchars()` to neutralize Cross-Site Scripting (XSS) attempts.

*Educational Note: The current implementation is designed for academic purposes. For a real production environment, it would be necessary to implement secure CSRF tokens, password hashing, and strictly isolate database credentials.*

## Managing Complex Operations
*   **Atomic Transactions:** The `conferma.php` page uses MySQL transactions (`mysqli_begin_transaction()`, `commit()`, `rollback()`) to ensure that multiple operations (such as deducting quantities from the warehouse and updating the user's balance) occur atomically.
*   **Cart and Dynamic Saving:** The "cart" in `domanda.php` is saved in the session. JavaScript intercepts asynchronous changes for automatic saving of choices, preventing accidental data loss.
*   **Time Filters:** Consistent implementation of date filters (start and end) on key pages (`lista.php`, `offerta.php`, `domanda.php`).

## User Experience (UX) and Accessibility
The interface has been designed to be clean and accessible:

*   **Home/Guide Separation:** `home.php` has a clean and evocative design, while operational details have been delegated to `guida.php`. This streamlines the UI for frequent users.
*   **Accessibility and Theme:** Dominance of the color green (linked to ecology) with high-contrast combinations to help users with visual impairments. ARIA attributes have been widely used for screen reader compatibility.
*   **Responsiveness:** Use of relative units (`em`, `vh`, `vw`) and fluid layouts.
*   **Printer-Friendly:** Specific CSS rules (`@media print`) optimize printing by hiding the header, footer, and unnecessary buttons, and forcing readable fonts on paper (serif, pt) with strong black/white contrast.

## Other Technical Choices
*   **HTTP Methods:** Forms strictly use the POST method for sending sensitive or state data, reserving the GET method exclusively for filtering and search forms (e.g., date filters).
*   **Database Privileges:** Queries are executed using MySQL accounts with the minimum necessary privileges (Principle of Least Privilege).
*   **Flexible Logic:** In `offerta.php`, the cost of materials can be set to 0 to allow for the donation of scraps.
*   **Login vs Registration Pattern:** The validation in `login.js` is deliberately slightly more permissive than in `registra.js` to accommodate and not block pre-existing profiles created under old policies (e.g., special characters in the database).
