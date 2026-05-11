/**
 * AMD module to dynamically swap activity icons using aggressive DOM replacement.
 *
 * @module     local_courseicons/swapper
 * @copyright  2026 Yeison Díaz
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    return {
        init: function(icons) {
            if (!icons || icons.length === 0) return;

            const applyIcons = () => {
                icons.forEach(function(icon) {
                    // FIX: Selector estricto para no afectar los menús desplegables.
                    const wrappers = document.querySelectorAll(
                        '#module-' + icon.cmid + ', ' + 
                        '.activity-item[data-id="' + icon.cmid + '"]'
                    );
                    
                    wrappers.forEach(function(wrapper) {
                        if (wrapper.dataset.courseiconApplied === icon.url) return;

                        const tileIcon = wrapper.querySelector('.tile-icon');
                        const m4Container = wrapper.querySelector('.activityiconcontainer');

                        // 1. ELIMINAR FONDOS DE TODOS LOS CONTENEDORES (Adiós fondo rosa).
                        [tileIcon, m4Container].forEach(function(container) {
                            if (container) {
                                container.style.setProperty('background-color', 'transparent', 'important');
                                container.style.setProperty('background', 'transparent', 'important');
                                container.style.setProperty('box-shadow', 'none', 'important');
                                container.style.setProperty('border', 'none', 'important');
                            }
                        });

                        // 2. BUSCAR O CREAR LA IMAGEN (Ignorando iconos del menú de ajustes).
                        let img = null;
                        const possibleImgs = wrapper.querySelectorAll('img.activityicon, img.icon');
                        for (let i = 0; i < possibleImgs.length; i++) {
                            if (!possibleImgs[i].closest('.action-menu') && !possibleImgs[i].closest('.dropdown-menu')) {
                                img = possibleImgs[i];
                                break;
                            }
                        }

                        if (!img) {
                            const target = m4Container || tileIcon;
                            if (target) {
                                target.innerHTML = '<img src="' + icon.url + '" class="activityicon">';
                                img = target.querySelector('img');
                            }
                        }

                        // 3. APLICAR TAMAÑOS INTELIGENTES SEGÚN EL FORMATO.
                        if (img) {
                            img.src = icon.url;
                            img.srcset = '';
                            img.style.setProperty('filter', 'none', 'important');
                            img.style.setProperty('object-fit', 'contain', 'important');
                            img.style.setProperty('border-radius', '0', 'important');

                            if (tileIcon || wrapper.closest('li.subtile')) {
                                // A. FORMATO MOSAICOS (Tiles) -> Lupa gigante.
                                img.style.setProperty('width', '100%', 'important');
                                img.style.setProperty('height', '110px', 'important');
                                img.style.setProperty('transform', 'scale(1.4)', 'important');
                                img.style.setProperty('padding', '0', 'important');
                                img.style.setProperty('margin', '0', 'important');
                                img.style.setProperty('max-width', 'none', 'important'); 

                                if (tileIcon) {
                                    tileIcon.style.setProperty('display', 'flex', 'important');
                                    tileIcon.style.setProperty('justify-content', 'center', 'important');
                                    tileIcon.style.setProperty('align-items', 'center', 'important');
                                    tileIcon.style.setProperty('width', '100%', 'important');
                                }
                            } else if (m4Container) {
                                // B. MOODLE 4 STANDARD (Temas, Semanas) -> Tamaño idéntico al original nativo (24px).
                                img.style.setProperty('width', '24px', 'important');
                                img.style.setProperty('height', '24px', 'important');
                                img.style.setProperty('transform', 'none', 'important'); // Quitamos la lupa.
                                img.style.setProperty('padding', '0', 'important');
                                img.style.setProperty('margin', '0', 'important');
                                
                                // Centramos perfectamente los 24px dentro del contenedor.
                                m4Container.style.setProperty('display', 'flex', 'important');
                                m4Container.style.setProperty('justify-content', 'center', 'important');
                                m4Container.style.setProperty('align-items', 'center', 'important');
                            } else {
                                // C. LEGACY MOODLE 3 O TEMAS EN LÍNEA -> Tamaño fijo alineado.
                                img.style.setProperty('width', '36px', 'important');
                                img.style.setProperty('height', '36px', 'important');
                                img.style.setProperty('transform', 'none', 'important');
                                img.style.setProperty('margin-right', '12px', 'important');
                                img.style.setProperty('vertical-align', 'middle', 'important');
                            }
                        }

                        if (img && img.parentElement && !m4Container && !tileIcon) {
                             img.parentElement.style.setProperty('background-color', 'transparent', 'important');
                             img.parentElement.style.setProperty('background', 'transparent', 'important');
                        }

                        wrapper.dataset.courseiconApplied = icon.url;
                    });
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', applyIcons);
            } else {
                applyIcons();
            }

            let isApplying = false;
            if (typeof MutationObserver !== 'undefined') {
                const observer = new MutationObserver(function() { 
                    if (isApplying) return;
                    isApplying = true;
                    applyIcons(); 
                    setTimeout(function() { isApplying = false; }, 50);
                });
                observer.observe(document.body, { childList: true, subtree: true });
            }
        }
    };
});
