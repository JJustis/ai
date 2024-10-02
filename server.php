<?php
session_start();
//header('Content-Type: application/json');

// Database connection details
$servername = "localhost";
$username = "root";
$password = ""; // Use your MySQL password
$dbname = "reservesphp";

// Create a connection to the MySQL database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// File paths
$trainingDataFile = 'trainingdata.json';
$scoreMapFile = 'score_map.json';
$structureDataFile = 'structuredata.json';

// Handle incoming requests
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? null;

// Helper function to read JSON files
function readJSONFile($filePath) {
    return file_exists($filePath) ? json_decode(file_get_contents($filePath), true) : [];
}

// Helper function to save data to JSON files
function saveJSONFile($filePath, $data) {
    file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
}

// Helper function to read training data
function readTrainingData() {
    global $trainingDataFile;
    return readJSONFile($trainingDataFile);
}

// Helper function to save a sentence pair to trainingdata.json
function saveSentencePair($userSentence, $aiResponse) {
    global $trainingDataFile;
    $trainingData = readTrainingData();
    $trainingData[] = ['User' => $userSentence, 'AI' => $aiResponse];
    saveJSONFile($trainingDataFile, $trainingData);
}

// Helper function to get word type and related words from the database
function getWordTypeAndRelatedWords($word, $conn) {
    $stmt = $conn->prepare("SELECT type, related_word FROM word WHERE word = ?");
    $stmt->bind_param('s', $word);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return ['type' => 'unknown', 'related_word' => ''];
}

// Helper function to add word types and related words to each word in a sentence
function addWordTypesAndRelatedWordsToSentence($sentence, $conn) {
    $words = explode(' ', $sentence);
    $typedWords = [];
    $relatedWords = [];

    foreach ($words as $word) {
        $wordData = getWordTypeAndRelatedWords($word, $conn);
        $typedWords[] = "$word:" . $wordData['type'];
        $relatedWords = array_merge($relatedWords, explode(',', $wordData['related_word']));
    }

    return ['typedSentence' => implode(' ', $typedWords), 'relatedWords' => $relatedWords];
}

// Helper function to generate AI response using related words
function generateAIResponseUsingRelatedWords($userSentence, $conn) {
    $relatedWordsData = addWordTypesAndRelatedWordsToSentence($userSentence, $conn);
    $relatedWords = $relatedWordsData['relatedWords'];
    $response = '';

    // Construct the response using related words
    foreach ($relatedWords as $relatedWord) {
        $response .= $relatedWord . ' ';
    }

    return trim($response);
}

// Helper function to generate a compressed unique ID based on sentence characteristics
function generateCompressedUniqueID($sentence, $traits) {
    $combinedTraits = $sentence . implode('', $traits);
    return substr(hash('sha256', $combinedTraits), 0, 16);  // 16-character unique ID
}

// Helper function to calculate similarity score between two sentences based on word types and related words
function calculateSimilarityScore($sentence1, $sentence2, $conn) {
    $sentenceData1 = addWordTypesAndRelatedWordsToSentence($sentence1, $conn);
    $sentenceData2 = addWordTypesAndRelatedWordsToSentence($sentence2, $conn);

    $words1 = explode(' ', $sentenceData1['typedSentence']);
    $words2 = explode(' ', $sentenceData2['typedSentence']);

    // Calculate similarity based on word types and related words
    $similarityScore = 0;
    foreach ($words1 as $wordType1) {
        foreach ($words2 as $wordType2) {
            if ($wordType1 === $wordType2) {
                $similarityScore += 1; // Full match
            } elseif (in_array(explode(':', $wordType1)[0], $sentenceData2['relatedWords'])) {
                $similarityScore += 0.5; // Partial match with related word
            }
        }
    }

    return $similarityScore;
}

// Post-process the training data to generate similarity scores and unique IDs
function postProcessTrainingData($conn) {
    global $trainingDataFile, $scoreMapFile;
    $trainingData = readTrainingData();
    $scoreMap = [];

    for ($i = 0; $i < count($trainingData); $i++) {
        for ($j = $i + 1; $j < count($trainingData); $j++) {
            $userSentence1 = $trainingData[$i]['User'];
            $aiResponse1 = $trainingData[$i]['AI'];
            $userSentence2 = $trainingData[$j]['User'];
            $aiResponse2 = $trainingData[$j]['AI'];

            // Calculate similarity score for sentences
            $similarityScore1 = calculateSimilarityScore($userSentence1, $userSentence2, $conn);
            $similarityScore2 = calculateSimilarityScore($aiResponse1, $aiResponse2, $conn);

            // Generate unique IDs for each comparison
            $uniqueID1 = generateCompressedUniqueID($userSentence1, [$userSentence2, $similarityScore1]);
            $uniqueID2 = generateCompressedUniqueID($aiResponse1, [$aiResponse2, $similarityScore2]);

            // Save to score map
            $scoreMap[] = [
                'uniqueID' => $uniqueID1,
                'Line1' => $i,
                'Line2' => $j,
                'SimilarityScore' => $similarityScore1,
                'MatchedWords' => array_intersect(explode(' ', $userSentence1), explode(' ', $userSentence2))
            ];

            $scoreMap[] = [
                'uniqueID' => $uniqueID2,
                'Line1' => $i,
                'Line2' => $j,
                'SimilarityScore' => $similarityScore2,
                'MatchedWords' => array_intersect(explode(' ', $aiResponse1), explode(' ', $aiResponse2))
            ];
        }
    }

    // Save score map to JSON file
    saveJSONFile($scoreMapFile, $scoreMap);
    echo json_encode(['success' => true, 'message' => 'Training data post-processed and similarity scores calculated.']);
}

// Handle incoming user input and generate AI response
if ($action === 'saveUserInput') {
    $userSentence = $data['sentence'] ?? '';

    if (!empty($userSentence)) {
        // Generate AI response using related words
        $aiResponse = generateAIResponseUsingRelatedWords($userSentence, $conn);

        // Save the user input and AI response to trainingdata.json
        saveSentencePair($userSentence, $aiResponse);

        echo json_encode(['success' => true, 'message' => 'User input and AI response saved.', 'aiResponse' => $aiResponse]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User input cannot be empty.']);
}

// Handle post-processing action
elseif ($action === 'postProcessTrainingData') {
    postProcessTrainingData($conn);
}

$conn->close();
?>
