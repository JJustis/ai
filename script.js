// script.js
function startPostProcessing() {
    logAction('Starting post-processing...');

    fetch('trainingdata.json')
        .then(response => response.json())
        .then(data => {
            const trainingData = data.filter(line => line.user); // Filter out empty lines

            // Process training data using Brain.js
            const net = new brain.recurrent.LSTM(); // Create a new LSTM network

            // Format the training data
            const formattedTrainingData = trainingData.map(entry => {
                return { input: entry.user, output: entry.ai };
            });

            // Train the network using the formatted training data
            net.train(formattedTrainingData, {
                iterations: 50,
                errorThresh: 0.011, // Adjust for better performance
            });

            // Test with a new input to generate a response
            const userInput = document.getElementById('userInput').value;
            if (userInput) {
                // Use Brain.js to generate the third sentence based on the model
                const response = net.run(userInput);
                logAction('Generated Brain.js Response: ' + response);

                // Save the response back to the server
                saveBrainJSResponse(userInput, response);
            } else {
                logAction('No user input provided for testing Brain.js response.');
            }
        })
        .catch(error => logAction('Error during post-processing: ' + error));
}

function saveBrainJSResponse(userInput, brainJSResponse) {
    fetch('server.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'saveBrainJSResponse', sentence: userInput, brainJSResponse: brainJSResponse })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            logAction('Brain.js response saved to server successfully.');
        } else {
            logAction('Failed to save Brain.js response: ' + data.message);
        }
    })
    .catch(error => logAction('Error saving Brain.js response: ' + error));
}

// Function to submit user input to the server
function submitUserInput() {
    const userInput = document.getElementById('userInput').value;

    if (!userInput) {
        logAction('Please enter a sentence.');
        return;
    }

    fetch('server.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'saveUserInput', sentence: userInput })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            logAction('User input saved successfully.');
            viewTrainingData(); // Refresh the training data view
        } else {
            logAction('Failed to save user input: ' + data.message);
        }
    })
    .catch(error => logAction('Error during saving user input: ' + error));
}

// Function to view the training data
function viewTrainingData() {
    fetch('trainingdata.json')
    .then(response => response.json())
    .then(data => document.getElementById('trainingData').innerText = JSON.stringify(data, null, 2))
    .catch(error => logAction('Failed to load training data: ' + error));
}

// Function to view the score map
function viewScoreMap() {
    fetch('score_map.json')
    .then(response => response.json())
    .then(data => document.getElementById('scoreMap').innerText = JSON.stringify(data, null, 2))
    .catch(error => logAction('Failed to load score map: ' + error));
}

// Function to log actions in the UI
function logAction(message) {
    const logList = document.getElementById('logList');
    const logItem = document.createElement('li');
    logItem.className = 'log';
    logItem.textContent = message;
    logList.appendChild(logItem);
}
