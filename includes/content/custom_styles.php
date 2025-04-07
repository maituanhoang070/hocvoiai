<?php
// Archivo: includes/content/custom_styles.php
?>
<style>
/* Reemplazo para clases de Tailwind que no funcionan */
/* relative group */
.custom-dropdown {
    position: relative;
}
.custom-dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    min-width: 12rem;
    background-color: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    z-index: 50;
}
.custom-dropdown:hover .custom-dropdown-content {
    display: block;
}

/* flex items-center focus:outline-none */
.custom-button {
    display: flex;
    align-items: center;
}
.custom-button:focus {
    outline: none;
}

/* Otros estilos útiles */
.custom-menu-item {
    display: block;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    transition: background-color 0.2s;
}
.custom-menu-item:hover {
    background-color: var(--hover-color);
}
</style>