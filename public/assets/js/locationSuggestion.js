document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('area_name');
            const suggestions = document.getElementById('locationSuggestions');
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
    
            let suggestionMap = {};
    
            input.addEventListener('input', function () {
                const query = input.value.trim();
                if (query.length < 3) {
                    suggestions.innerHTML = '';
                    suggestions.style.display = 'none';
                    return;
                }
    
                fetch(`https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=50`)
                    .then(response => response.json())
                    .then(data => {
                        suggestionMap = {};
                        suggestions.innerHTML = '';
    
                        const features = data.features || [];
                        if (features.length === 0) {
                            suggestions.style.display = 'none';
                            return;
                        }
    
                        features.forEach(item => {
                            // Build and clean the label
                            let rawLabel = item.properties.name + ', ' + (item.properties.city || item.properties.country || '');
                            let cleanedLabel = rawLabel
                                .replace(/\b(Tehsil|District|Division)\b/gi, '') // Remove keywords
                                .replace(/\s+/g, ' ') // Remove extra spaces
                                .trim();
    
                            suggestionMap[cleanedLabel] = item;
    
                            const li = document.createElement('li');
                            li.textContent = cleanedLabel;
                            li.dataset.city = cleanedLabel;
                            li.classList.add('list-group-item');
                            suggestions.appendChild(li);
                        });
    
                        suggestions.style.display = 'block';
                    })
                    .catch(err => {
                        console.error('Photon API error:', err);
                        suggestions.innerHTML = '';
                        suggestions.style.display = 'none';
                    });
            });
    
            document.addEventListener('click', function (e) {
                const clickedItem = e.target.closest('#locationSuggestions li');
                if (clickedItem) {
                    const label = clickedItem.dataset.city;
                    input.value = label;
                    suggestions.innerHTML = '';
                    suggestions.style.display = 'none';
    
                    const selectedItem = suggestionMap[label];
                    if (selectedItem) {
                        const [lng, lat] = selectedItem.geometry.coordinates;
                        latitudeInput.value = lat;
                        longitudeInput.value = lng;
                    }
                } else if (!e.target.closest('#locationSuggestions') && e.target !== input) {
                    suggestions.innerHTML = '';
                    suggestions.style.display = 'none';
                }
            });
        });