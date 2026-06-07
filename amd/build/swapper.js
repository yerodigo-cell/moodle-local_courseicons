// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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
                    const wrappers = document.querySelectorAll(
                        '#module-' + icon.cmid + ', ' + 
                        '.activity-item[data-id="' + icon.cmid + '"]'
                    );
                    
                    wrappers.forEach(function(wrapper) {
                        if (wrapper.dataset.courseiconApplied === icon.url) return;

                        const tileIcon = wrapper.querySelector('.tile-icon');
                        const m4Container = wrapper.querySelector('.activityiconcontainer');

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
                                const newImg = document.createElement('img');
                                newImg.src = '';
                                newImg.className = 'activityicon';
                                newImg.alt = '';
                                target.appendChild(newImg);
                                img = newImg;
                            }
                        }

                        if (img) {
                            // Synchronous load without delays or opacities. CSS already did the visual work.
                            img.src = icon.url;
                            img.srcset = '';
                            img.style.setProperty('filter', 'none', 'important');
                            img.style.setProperty('object-fit', 'contain', 'important');
                            img.style.setProperty('border-radius', '0', 'important');

                            if (tileIcon || wrapper.closest('li.subtile')) {
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
                                img.style.setProperty('width', '24px', 'important');
                                img.style.setProperty('height', '24px', 'important');
                                img.style.setProperty('transform', 'none', 'important');
                                img.style.setProperty('padding', '0', 'important');
                                img.style.setProperty('margin', '0', 'important');
                                
                                m4Container.style.setProperty('display', 'flex', 'important');
                                m4Container.style.setProperty('justify-content', 'center', 'important');
                                m4Container.style.setProperty('align-items', 'center', 'important');
                            } else {
                                img.style.setProperty('width', '36px', 'important');
                                img.style.setProperty('height', '36px', 'important');
                                img.style.setProperty('transform', 'none', 'important');
                                img.style.setProperty('margin-right', '12px', 'important');
                                img.style.setProperty('vertical-align', 'middle', 'important');
                            }

                            if (img.parentElement && !m4Container && !tileIcon) {
                                 img.parentElement.style.setProperty('background-color', 'transparent', 'important');
                                 img.parentElement.style.setProperty('background', 'transparent', 'important');
                            }
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

            if (typeof MutationObserver !== 'undefined') {
                let debounceTimer;
                const observer = new MutationObserver(function(mutations) {
                    let hasNewNodes = false;
                    for (let i = 0; i < mutations.length; i++) {
                        if (mutations[i].addedNodes && mutations[i].addedNodes.length > 0) {
                            hasNewNodes = true;
                            break;
                        }
                    }

                    if (hasNewNodes) {
                        clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(function() {
                            observer.disconnect();
                            applyIcons();
                            const container = document.querySelector('.course-content') ||
                                document.querySelector('#region-main') || document.body;
                            observer.observe(container, { childList: true, subtree: true });
                        }, 150);
                    }
                });
                
                const container = document.querySelector('.course-content') ||
                    document.querySelector('#region-main') || document.body;
                observer.observe(container, { childList: true, subtree: true });
            }
        }
    };
});
