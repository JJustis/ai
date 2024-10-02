<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Sentence Analysis and Processing</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }

        #container {
            width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        button {
            padding: 10px 15px;
            margin: 5px;
            border: none;
            background-color: #3498db;
            color: #fff;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #2980b9;
        }

        #logs, #trainingData, #scoreMap {
            margin-top: 20px;
            height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background: #f4f4f4;
        }

        .log {
            font-size: 14px;
            padding: 5px;
            border-left: 4px solid #3498db;
            background: #ecf0f1;
            margin: 5px 0;
        }
    </style>
    <!-- Include Brain.js via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/brain.js@2.0.0-beta.10"></script>
</head>
<body>
    <div id="container">
        <h2>AI Sentence Analysis and Processing</h2>
        <input type="text" id="userInput" placeholder="Enter a sentence..." style="width: 70%; padding: 10px; margin-bottom: 10px;">
        <button onclick="submitUserInput()">Submit</button>
        <button onclick="startPostProcessing()">Start Post-Processing</button>
        <button onclick="viewTrainingData()">View Training Data</button>
        <button onclick="viewScoreMap()">View Score Map</button>

        <h3>Training Data</h3>
        <div id="trainingData"></div>

        <h3>Score Map</h3>
        <div id="scoreMap"></div>

        <h3>Logs</h3>
        <div id="logs">
            <ul id="logList"></ul>
        </div>
    </div>

    <script>
        // Function to log actions in the UI
        function logAction(message) {
            const logList = document.getElementById('logList');
            const logItem = document.createElement('li');
            logItem.className = 'log';
            logItem.textContent = message;
            logList.appendChild(logItem);
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
                    logAction('User input saved. AI Response: ' + data.aiResponse);
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
                .then(data => {
                    const displayData = data.map((item, index) => `Line ${index + 1}: User: ${item.user} | AI: ${item.ai}`).join('\n');
                    document.getElementById('trainingData').innerText = displayData;
                })
                .catch(error => logAction('Failed to load training data: ' + error));
        }

        // Function to view the score map
        function viewScoreMap() {
            fetch('score_map.json')
                .then(response => response.json())
                .then(data => {
                    const displayData = Object.entries(data)
                        .map(([key, value]) => `UniqueID: ${key} | Sentence: ${value.sentence} | HighScoringWords: ${value.highScoringWords.join(', ')}`)
                        .join('\n');
                    document.getElementById('scoreMap').innerText = displayData;
                })
                .catch(error => logAction('Failed to load score map: ' + error));
        }

        // Function to start post-processing using Brain.js
        function startPostProcessing() {
            logAction('Starting post-processing...');

            fetch('trainingdata.json')
                .then(response => response.json())
                .then(data => {
                    const trainingData = data.filter(entry => entry.user); // Filter out empty lines

                    // Process training data using Brain.js
                    const net = new brain.recurrent.LSTM(); // Create a new LSTM network

                    // Format the training data
                    const formattedTrainingData = trainingData.map(entry => ({
                        input: entry.user,
                        output: entry.ai
                    }));

                    // Train the network using the formatted training data
                    net.train(formattedTrainingData, {
                        iterations: 5,
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

        // Function to send the Brain.js response back to the server
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
    </script>
</body>
</html>
