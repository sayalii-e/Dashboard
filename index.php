<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placement Agencies Dashboard</title>
    <style>
        body { font-family: sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #333; }
        .filters-section { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .filters-section h2, .filters-section h3 { margin-top: 0; color: #555; }
        .filter-group { margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;}
        .filter-group label { font-weight: bold; margin-right: 5px; min-width: 100px;}
        .filter-group input[type="text"], .filter-group select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            flex-grow: 1;
        }
        .filter-group .subfilter-select { min-width: 100px; flex-grow: 0.5; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f0f0f0; }
        .actions { margin-bottom: 20px; text-align: right; }
        .actions button, #applyFiltersBtn { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 5px; }
        .actions button:hover, #applyFiltersBtn:hover { background-color: #0056b3; }
        #loading { text-align: center; display: none; margin-top: 20px;}
    </style>
</head>
<body>
    <div class="container">
        <h1>Placement Agencies Dashboard</h1>

        <div class="actions">
            <button id="exportCsvBtn">Export to CSV</button>
        </div>

        <div class="filters-section">
            <h2>Active Filters</h2>
            <div class="filter-group">
                <label for="company_name_input">Company:</label>
                <select id="company_name_filter_type" class="subfilter-select">
                    <option value="contains">Contains</option>
                    <option value="startsWith">Starts With</option>
                    <option value="includes">Includes</option> <!-- 'Includes' for text often means 'contains' -->
                    <option value="excludes">Excludes</option>
                </select>
                <input type="text" id="company_name_input" placeholder="Enter company name">
            </div>
            <div class="filter-group">
                <label for="city_input">City:</label>
                <select id="city_input">
                    <option value="">All Cities</option>
                    <!-- City options will be populated by JavaScript -->
                </select>
            </div>
            <div class="filter-group">
                <label for="name_input">Name:</label>
                <select id="name_filter_type" class="subfilter-select">
                    <option value="contains">Contains</option>
                    <option value="startsWith">Starts With</option>
                    <option value="includes">Includes</option>
                    <option value="excludes">Excludes</option>
                </select>
                <input type="text" id="name_input" placeholder="Enter name">
            </div>
            <div class="filter-group">
                <label for="website_input">Website:</label>
                <select id="website_filter_type" class="subfilter-select">
                    <option value="contains">Contains</option>
                    <option value="startsWith">Starts With</option>
                    <option value="includes">Includes</option>
                    <option value="excludes">Excludes</option>
                </select>
                <input type="text" id="website_input" placeholder="Enter website">
            </div>
        </div>

        <div class="filters-section">
            <h3>Remaining Filters (Dropdown)</h3>
            <div class="filter-group">
                <label for="email_input">Email:</label>
                <select id="email_filter_type" class="subfilter-select">
                    <option value="contains">Contains</option>
                    <option value="startsWith">Starts With</option>
                    <option value="includes">Includes</option>
                    <option value="excludes">Excludes</option>
                </select>
                <input type="text" id="email_input" placeholder="Enter email">
            </div>
            <div class="filter-group">
                <label for="mobile_input">Mobile:</label>
                <select id="mobile_filter_type" class="subfilter-select">
                    <option value="contains">Contains</option>
                    <option value="startsWith">Starts With</option>
                    <option value="includes">Includes</option>
                    <option value="excludes">Excludes</option>
                </select>
                <input type="text" id="mobile_input" placeholder="Enter mobile number">
            </div>
            <div class="filter-group">
                <label for="address_input">Address:</label>
                <select id="address_filter_type" class="subfilter-select">
                    <option value="contains">Contains</option>
                    <option value="startsWith">Starts With</option>
                    <option value="includes">Includes</option>
                    <option value="excludes">Excludes</option>
                </select>
                <input type="text" id="address_input" placeholder="Enter address">
            </div>
        </div>
        <div style="text-align: center; margin-top:15px;">
             <button id="applyFiltersBtn">Apply Filters</button>
        </div>


        <div id="loading">Loading data...</div>
        <div class="table-container">
            <table id="dataTable">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Name</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>City</th>
                        <th>Address</th>
                        <th>Pincode</th>
                        <th>Website</th>
                        <th>Category</th>
                        <th>State</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data rows will be populated by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const applyFiltersBtn = document.getElementById('applyFiltersBtn');
            const exportCsvBtn = document.getElementById('exportCsvBtn');
            const loadingDiv = document.getElementById('loading');
            const dataTableBody = document.querySelector('#dataTable tbody');

            // Corrected filter IDs to match the HTML
            const filters = {
                company_name: { input: document.getElementById('company_name_input'), typeSelect: document.getElementById('company_name_filter_type') },
                city: { input: document.getElementById('city_input'), typeSelect: null },
                name: { input: document.getElementById('name_input'), typeSelect: document.getElementById('name_filter_type') },
                website: { input: document.getElementById('website_input'), typeSelect: document.getElementById('website_filter_type') },
                email: { input: document.getElementById('email_input'), typeSelect: document.getElementById('email_filter_type') },
                mobile: { input: document.getElementById('mobile_input'), typeSelect: document.getElementById('mobile_filter_type') },
                address: { input: document.getElementById('address_input'), typeSelect: document.getElementById('address_filter_type') }
            };

            function fetchCities() {
                loadingDiv.style.display = 'block';
                fetch('data.php?action=getCities')
                    .then(response => response.json())
                    .then(data => {
                        const citySelect = document.getElementById('city_input'); // Corrected ID
                        if (data.error) {
                            console.error('Error fetching cities:', data.error);
                            citySelect.innerHTML = '<option value="">Error loading cities</option>';
                            return;
                        }
                        citySelect.innerHTML = '<option value="">All Cities</option>';
                        if (data.cities && Array.isArray(data.cities)) {
                            data.cities.forEach(city => {
                                if(city) { // Ensure city is not null or empty
                                    const option = document.createElement('option');
                                    option.value = city;
                                    option.textContent = city;
                                    citySelect.appendChild(option);
                                }
                            });
                        } else {
                             console.error('Cities data is not in expected format:', data);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching cities:', error);
                        document.getElementById('city_input').innerHTML = '<option value="">Error loading cities</option>'; // Corrected ID
                    })
                    .finally(() => {
                        loadingDiv.style.display = 'none';
                    });
            }

            function fetchData() {
                loadingDiv.style.display = 'block';
                dataTableBody.innerHTML = '';

                const params = new URLSearchParams();
                params.append('action', 'getData');

                for (const key in filters) {
                    const filterInput = filters[key].input;
                    const filterValue = filterInput.value.trim();

                    if (filterValue) {
                        params.append(key, filterValue);
                        if (filters[key].typeSelect) { // For filters with sub-types
                            params.append(key + '_type', filters[key].typeSelect.value);
                        }
                    } else if (key === 'city' && filterInput.value) { // For city if "All Cities" is not selected
                         params.append(key, filterInput.value);
                    }
                }

                fetch('data.php?' + params.toString())
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            console.error('Error fetching data:', data.error);
                            dataTableBody.innerHTML = `<tr><td colspan="10" style="text-align:center;">Error: ${data.error}</td></tr>`;
                            return;
                        }
                        if (data.data && data.data.length === 0) {
                            dataTableBody.innerHTML = '<tr><td colspan="10" style="text-align:center;">No data found matching your criteria.</td></tr>';
                        } else if (data.data) {
                            data.data.forEach(row => {
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td>${row.company || ''}</td>
                                    <td>${row.name || ''}</td>
                                    <td>${row.mobile || ''}</td>
                                    <td>${row.email || ''}</td>
                                    <td>${row.city || ''}</td>
                                    <td>${row.address || ''}</td>
                                    <td>${row.pincode || ''}</td>
                                    <td>${row.website || ''}</td>
                                    <td>${row.category || ''}</td>
                                    <td>${row.State || ''}</td>
                                `;
                                dataTableBody.appendChild(tr);
                            });
                        } else {
                             dataTableBody.innerHTML = '<tr><td colspan="10" style="text-align:center;">Received unexpected data format.</td></tr>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching data:', error);
                        dataTableBody.innerHTML = '<tr><td colspan="10" style="text-align:center;">An error occurred while fetching data. Please try again.</td></tr>';
                    })
                    .finally(() => {
                        loadingDiv.style.display = 'none';
                    });
            }

            applyFiltersBtn.addEventListener('click', fetchData);

            exportCsvBtn.addEventListener('click', function() {
                const params = new URLSearchParams();
                params.append('action', 'exportCsv');
                // Add all current filters to the export URL
                 for (const key in filters) {
                    const filterInput = filters[key].input;
                    const filterValue = filterInput.value.trim();
                     if (filterValue) {
                        params.append(key, filterValue);
                        if (filters[key].typeSelect) {
                            params.append(key + '_type', filters[key].typeSelect.value);
                        }
                    } else if (key === 'city' && filterInput.value) {
                         params.append(key, filterInput.value);
                    }
                }
                window.location.href = 'data.php?' + params.toString();
            });

            fetchCities();
            fetchData(); // Initial data load
        });
    </script>
</body>
</html>
