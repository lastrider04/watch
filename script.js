document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM fully loaded and script.js executing. V3");

    // --- GLOBALS & ELEMENTS ---
    const liveSearchInput = document.getElementById('liveSearchInput');
    const liveSearchResultsContainer = document.getElementById('liveSearchResultsContainer');
    const watchListDisplayUl = document.getElementById('watchListDisplay');
    const pickRandomButton = document.getElementById('pickRandomButton'); // **** ENSURE THIS ID IS ON YOUR BUTTON IN INDEX.PHP ****
    const pickerSection = document.querySelector('.picker-section');
    const globalMessageArea = document.getElementById('globalMessageArea');
    const emptyWatchlistMessage = document.getElementById('emptyWatchlistMessage');

    const animationCanvas = document.getElementById('animationCanvas');
    const animatedPoster = document.getElementById('animatedPoster');
    const animatedTitle = document.getElementById('animatedTitle');
    const animatedMediaTypeDisplay = document.getElementById('animatedMediaType');
    const randomPickerForm = document.getElementById('randomPickerForm');
    const selectedItemIdInput = document.getElementById('selectedItemIdInput');
    const selectedItemMediaTypeInput = document.getElementById('selectedItemMediaTypeInput');

    let debounceTimeout;
    let pickerWatchlistItems = [];
    let lastPickedItem = null;
    let animationInterval = null; // Ensure this is declared if used globally within the listener

    // --- CONSOLE LOG TO CHECK IF pickRandomButton ELEMENT IS FOUND ---
    if (pickRandomButton) {
        console.log("pickRandomButton element FOUND in the DOM.");
    } else {
        console.error("pickRandomButton element (id='pickRandomButton') NOT FOUND in the DOM! Event listener cannot be attached.");
    }

    try {
        const lastPickedItemData = document.body.dataset.lastPickedItem;
        if (lastPickedItemData && lastPickedItemData !== 'null' && lastPickedItemData.trim() !== '') {
            lastPickedItem = JSON.parse(lastPickedItemData);
        }
    } catch (e) {
        console.error("Error parsing last picked item data:", e);
        lastPickedItem = null;
    }

    // ... (displayGlobalMessage, live search, ajax add to watchlist, addItemToWatchlistDOM - IDENTICAL to previous working version)
    function displayGlobalMessage(message, type = 'info', duration = 4000) { if (!globalMessageArea) return; globalMessageArea.textContent = message; globalMessageArea.className = 'message'; if (type === 'success') globalMessageArea.classList.add('message-success'); else if (type === 'error') globalMessageArea.classList.add('message-error'); else globalMessageArea.classList.add('message-info'); globalMessageArea.style.display = 'block'; setTimeout(() => { globalMessageArea.style.display = 'none'; }, duration); }
    if (liveSearchInput) { liveSearchInput.addEventListener('input', function() { const query = this.value.trim(); clearTimeout(debounceTimeout); if (query.length < 2) { if(liveSearchResultsContainer) liveSearchResultsContainer.innerHTML = ''; return; } if(liveSearchResultsContainer) liveSearchResultsContainer.innerHTML = '<p class="searching-text">Searching...</p>'; debounceTimeout = setTimeout(() => { fetch(`index.php?action=ajax_live_search&query=${encodeURIComponent(query)}`).then(response => { if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`); return response.json(); }).then(data => { if(!liveSearchResultsContainer) return; liveSearchResultsContainer.innerHTML = ''; if (data.items && data.items.length > 0) { const ul = document.createElement('ul'); data.items.forEach(item => { const li = document.createElement('li'); li.innerHTML = ` <img src="${item.poster_url}" alt="${item.title} Poster" class="search-result-poster"> <span>${item.title} (${item.year}) <span class="media-type-badge">${item.media_type.toUpperCase()}</span></span> <button class="add-btn-ajax" data-item-id="${item.id}" data-item-title="${item.title}" data-item-poster-url="${item.poster_url}" data-item-media-type="${item.media_type}">Add</button> `; ul.appendChild(li); }); liveSearchResultsContainer.appendChild(ul); } else { liveSearchResultsContainer.innerHTML = '<p>No items found.</p>'; } }).catch(error => { console.error('Live search error:', error); if(liveSearchResultsContainer) liveSearchResultsContainer.innerHTML = '<p>Error fetching search results.</p>'; displayGlobalMessage('Error fetching search results.', 'error'); }); }, 500); }); }
    if (liveSearchResultsContainer) { liveSearchResultsContainer.addEventListener('click', function(event) { if (event.target.classList.contains('add-btn-ajax')) { const button = event.target; button.disabled = true; button.textContent = 'Adding...'; const itemId = button.dataset.itemId; const itemTitle = button.dataset.itemTitle; const itemPosterUrl = button.dataset.itemPosterUrl; const itemMediaType = button.dataset.itemMediaType; const formData = new FormData(); formData.append('action', 'ajax_add_to_watchlist'); formData.append('item_id', itemId); formData.append('item_title', itemTitle); formData.append('item_poster_url', itemPosterUrl); formData.append('item_media_type', itemMediaType); fetch('index.php', { method: 'POST', body: formData }).then(response => { if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`); return response.json(); }).then(data => { if (data.success) { displayGlobalMessage(data.message, 'success'); addItemToWatchlistDOM(data.item); liveSearchResultsContainer.innerHTML = ''; if (liveSearchInput) liveSearchInput.value = ''; } else { displayGlobalMessage(data.message, 'error'); } }).catch(error => { console.error('Add to watchlist error:', error); displayGlobalMessage('Error adding item. Please try again.', 'error'); }).finally(() => { if (liveSearchResultsContainer.contains(button)) { button.disabled = false; button.textContent = 'Add'; } }); } }); }
    function addItemToWatchlistDOM(itemData) { if (!watchListDisplayUl) return; const currentEmptyMsg = document.getElementById('emptyWatchlistMessage'); if (currentEmptyMsg) { currentEmptyMsg.style.display = 'none'; if(watchListDisplayUl.contains(currentEmptyMsg)) { watchListDisplayUl.removeChild(currentEmptyMsg); } } const li = document.createElement('li'); li.classList.add('watchlist-item-clickable'); const escapedTitleForConfirm = itemData.title.replace(/'/g, "\\'").replace(/"/g, '\\"'); li.innerHTML = ` <form method="POST" action="index.php" class="view-details-form"> <input type="hidden" name="action" value="show_watchlist_item_details"> <input type="hidden" name="item_id_to_show" value="${itemData.id}"> <input type="hidden" name="item_media_type_to_show" value="${itemData.media_type}"> <button type="submit" class="watchlist-item-button"> <img src="${itemData.poster_url_for_list}" alt="${itemData.title} Poster" class="watchlist-poster"> <span class="watchlist-title-text"> ${itemData.title} <span class="media-type-badge">${itemData.media_type.toUpperCase()}</span> </span> </button> </form> <form method="POST" action="index.php" class="remove-form" onsubmit="return confirm('Remove ${escapedTitleForConfirm} (${itemData.media_type.toUpperCase()}) from watchlist?');"> <input type="hidden" name="action" value="remove"> <input type="hidden" name="item_id_to_remove" value="${itemData.id}"> <input type="hidden" name="item_media_type_to_remove" value="${itemData.media_type}"> <button type="submit" class="remove-btn">Remove</button> </form> `; watchListDisplayUl.appendChild(li); updatePickerVisibility(); }


    // --- RANDOM PICKER LOGIC ---
    function loadPickerWatchlistItems() {
        pickerWatchlistItems = [];
        if (watchListDisplayUl) {
            const listElements = watchListDisplayUl.querySelectorAll('li.watchlist-item-clickable .view-details-form'); // Target forms to get data
            listElements.forEach(formEl => {
                const titleSpan = formEl.querySelector('.watchlist-title-text');
                const posterImg = formEl.querySelector('.watchlist-poster');
                if (titleSpan && posterImg) {
                    let fullText = titleSpan.textContent || titleSpan.innerText;
                    let mediaTypeBadge = titleSpan.querySelector('.media-type-badge');
                    let cleanTitle = fullText;
                    if (mediaTypeBadge) {
                        cleanTitle = fullText.replace(mediaTypeBadge.textContent, '').trim();
                    }
                    pickerWatchlistItems.push({
                        id: formEl.querySelector('input[name="item_id_to_show"]').value,
                        title: cleanTitle,
                        poster_path: posterImg.src,
                        media_type: formEl.querySelector('input[name="item_media_type_to_show"]').value
                    });
                }
            });
        }
        // console.log("Picker watchlist items loaded ("+ pickerWatchlistItems.length +")");
    }

    if (pickRandomButton) {
        pickRandomButton.addEventListener('click', function() {
            // **** ADD THIS LOG AT THE VERY START OF THE LISTENER ****
            console.log("--- Decide button CLICKED! ---");

            loadPickerWatchlistItems(); // Ensure items are fresh
            console.log("Picker items count after load:", pickerWatchlistItems.length);

            if (pickerWatchlistItems.length === 0) {
                displayGlobalMessage("Your watchlist is empty! Add some items first.", "error");
                console.log("Watchlist empty, returning.");
                return;
            }
            if (!animationCanvas || !animatedPoster || !animatedTitle || !animatedMediaTypeDisplay || !selectedItemIdInput || !selectedItemMediaTypeInput || !randomPickerForm) {
                console.error("Picker animation elements not found! Cannot proceed.");
                displayGlobalMessage("Error initializing picker. Please refresh.", "error");
                return;
            }
            console.log("All picker animation elements found.");

            let candidatePickList = [...pickerWatchlistItems];
            if (pickerWatchlistItems.length > 1 && lastPickedItem) {
                const filteredList = pickerWatchlistItems.filter(item =>
                    !(String(item.id) === String(lastPickedItem.id) && item.media_type === lastPickedItem.media_type)
                );
                if (filteredList.length > 0) {
                    candidatePickList = filteredList;
                }
            }
            // console.log("Candidate pick list size:", candidatePickList.length);


            animationCanvas.style.display = 'flex';
            pickRandomButton.disabled = true;
            pickRandomButton.textContent = 'Deciding...';

            let animationEffectIndex = 0;
            const animationDuration = 3000; //ms
            const intervalTime = 100; //ms

            console.log("Starting animation interval...");
            if (animationInterval) clearInterval(animationInterval); // Clear any pre-existing interval

            animationInterval = setInterval(() => {
                animatedPoster.style.opacity = 0;
                animatedMediaTypeDisplay.style.opacity = 0;
                animatedTitle.style.opacity = 0;

                setTimeout(() => {
                    animationEffectIndex = (animationEffectIndex + 1) % pickerWatchlistItems.length;
                    const currentVisualItem = pickerWatchlistItems[animationEffectIndex];
                    animatedPoster.src = currentVisualItem.poster_path;
                    animatedTitle.textContent = currentVisualItem.title;
                    animatedMediaTypeDisplay.textContent = currentVisualItem.media_type.toUpperCase();
                    animatedPoster.style.opacity = 1;
                    animatedMediaTypeDisplay.style.opacity = 1;
                    animatedTitle.style.opacity = 1;
                }, intervalTime / 2);
            }, intervalTime);

            // After animation duration, pick the final item
            setTimeout(() => {
                console.log("Animation duration ended. Clearing interval.");
                if (animationInterval) {
                    clearInterval(animationInterval);
                    animationInterval = null;
                }

                const finalRandomIndex = Math.floor(Math.random() * candidatePickList.length);
                const selectedItem = candidatePickList[finalRandomIndex];
                console.log("CHOSEN ITEM for submission:", selectedItem.title, selectedItem.id, selectedItem.media_type);

                animatedPoster.style.opacity = 0;
                animatedMediaTypeDisplay.style.opacity = 0;
                animatedTitle.style.opacity = 0;

                setTimeout(() => { // Nested timeout for clean visual update
                    animatedPoster.src = selectedItem.poster_path;
                    animatedTitle.textContent = "Chosen: " + selectedItem.title;
                    animatedMediaTypeDisplay.textContent = selectedItem.media_type.toUpperCase();
                    console.log("Visuals updated to CHOSEN ITEM:", selectedItem.title);

                    animatedPoster.style.opacity = 1;
                    animatedMediaTypeDisplay.style.opacity = 1;
                    animatedTitle.style.opacity = 1;

                    selectedItemIdInput.value = selectedItem.id;
                    selectedItemMediaTypeInput.value = selectedItem.media_type;
                    console.log("Form values set for submission.");

                    setTimeout(() => {
                        console.log("Submitting form.");
                        if (randomPickerForm) {
                            randomPickerForm.submit();
                        } else {
                            console.error("Random picker form NOT FOUND!");
                            pickRandomButton.disabled = false;
                            pickRandomButton.textContent = 'Decide!';
                        }
                    }, 700);
                }, 50);

            }, animationDuration);
        });
    } else {
        // This else block will execute if pickRandomButton was null initially
        console.warn("pickRandomButton was not found when trying to attach event listener. Decide button will not work.");
    }


    // --- PAGE INITIALIZATION AND VISIBILITY CONTROL ---
    function hasWatchlistItems() {
        if (!watchListDisplayUl) return false;
        return watchListDisplayUl.querySelectorAll('li.watchlist-item-clickable').length > 0;
    }

    function updatePickerVisibility() {
        if (pickerSection) {
            if (hasWatchlistItems()) {
                // console.log("Watchlist has items, showing picker section.");
                pickerSection.style.display = 'block';
                if (emptyWatchlistMessage) emptyWatchlistMessage.style.display = 'none';
            } else {
                // console.log("Watchlist is empty, hiding picker section.");
                pickerSection.style.display = 'none';
                const phpGeneratedEmptyMsg = document.getElementById('emptyWatchlistMessage');
                if(phpGeneratedEmptyMsg) phpGeneratedEmptyMsg.style.display = 'block';
            }
        }
    }

    function initializePage() {
        // console.log("Initializing page...");
        if (!document.querySelector('.result-page')) {
            // console.log("On main page. Updating picker visibility.");
            loadPickerWatchlistItems();
            updatePickerVisibility();
        } else {
            // console.log("On result page. Hiding picker section if it exists.");
            if (pickerSection) pickerSection.style.display = 'none';
        }
    }

    initializePage();

    if (watchListDisplayUl) {
        const observer = new MutationObserver(function(mutationsList, observer) {
            loadPickerWatchlistItems();
            updatePickerVisibility();
        });
        observer.observe(watchListDisplayUl, { childList: true, subtree: false });
    }

});