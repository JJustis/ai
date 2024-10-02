<?php
// Initialize stop words array
$stopWords = array(
    'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you', 'your', 'yours',
    'yourself', 'yourselves', 'he', 'him', 'his', 'himself', 'she', 'her', 'hers',
    'herself', 'it', 'its', 'itself', 'they', 'them', 'their', 'theirs', 'themselves',
    'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those', 'am', 'is', 'are',
    'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'having', 'do', 'does',
    'did', 'doing', 'a', 'an', 'the', 'and', 'but', 'if', 'or', 'because', 'as', 'until',
    'while', 'of', 'at', 'by', 'for', 'with', 'about', 'against', 'between', 'into',
    'through', 'during', 'before', 'after', 'above', 'below', 'to', 'from', 'up', 'down',
    'in', 'out', 'on', 'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here',
    'there', 'when', 'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more',
    'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so',
    'than', 'too', 'very', 's', 't', 'can', 'will', 'just', 'don', 'should', 'now'
);

// Connect to the MySQL database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "reservesphp";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get the user input from the AJAX request
$userInput = $_GET['userInput'];

// Split user input into individual words
$inputWords = explode(' ', strtolower($userInput));

// Create arrays to store word definitions and relevance scores
$wordDefinitions = array();
$wordRelevanceScores = array();

// Loop through each word in the input and look up its definition in the 'word' table
foreach ($inputWords as $word) {
    // Skip stop words
    if (in_array($word, $stopWords)) {
        continue;
    }

    // Query to get the definition for each word
    $sql = "SELECT definition FROM word WHERE word='$word'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $wordDefinitions[$word] = $row['definition'];

            // Calculate relevance score for the word based on how often it appears in other definitions
            $relevanceSql = "SELECT COUNT(*) as count FROM word WHERE definition LIKE '%$word%'";
            $relevanceResult = $conn->query($relevanceSql);
            if ($relevanceResult && $relevanceResult->num_rows > 0) {
                $relevanceRow = $relevanceResult->fetch_assoc();
                $wordRelevanceScores[$word] = $relevanceRow['count'];
            }
        }
    }
}

// Helper function to convert a sentence into a word vector, excluding stop words
function getWordVector($sentence) {
    global $stopWords; // Reference the global stop words list
    $words = explode(' ', strtolower($sentence)); // Split sentence into words
    $wordCount = array();

    foreach ($words as $word) {
        if (in_array($word, $stopWords)) {
            continue; // Skip stop words
        }

        if (array_key_exists($word, $wordCount)) {
            $wordCount[$word] += 1; // Increment count if word already exists
        } else {
            $wordCount[$word] = 1; // Initialize count if word is new
        }
    }
    return $wordCount;
}

// Function to calculate cosine similarity between two word vectors
function cosineSimilarity($vector1, $vector2) {
    $uniqueWords = array_unique(array_merge(array_keys($vector1), array_keys($vector2)));
    $v1 = array_map(function($word) use ($vector1) { return isset($vector1[$word]) ? $vector1[$word] : 0; }, $uniqueWords);
    $v2 = array_map(function($word) use ($vector2) { return isset($vector2[$word]) ? $vector2[$word] : 0; }, $uniqueWords);

    $dotProduct = array_sum(array_map(function($val1, $val2) { return $val1 * $val2; }, $v1, $v2));
    $magnitude1 = sqrt(array_sum(array_map(function($val) { return $val * $val; }, $v1)));
    $magnitude2 = sqrt(array_sum(array_map(function($val) { return $val * $val; }, $v2)));

    if ($magnitude1 == 0 || $magnitude2 == 0) {
        return 0; // Return 0 similarity if one of the vectors has zero magnitude
    }

    return $dotProduct / ($magnitude1 * $magnitude2);
}

// Loop through each word's definition to find the most relevant sentence
$mostRelevantSentence = "";
$maxRelevanceScore = 0;

foreach ($wordDefinitions as $word => $definition) {
    $definitionVector = getWordVector($definition);

    // Calculate cosine similarity with the input vector
    $inputVector = getWordVector($userInput);
    $similarityScore = cosineSimilarity($definitionVector, $inputVector);

    // Adjust score based on the word's relevance score
    $relevanceScore = $similarityScore * ($wordRelevanceScores[$word] ?? 1);

    if ($relevanceScore > $maxRelevanceScore) {
        $maxRelevanceScore = $relevanceScore;
        $mostRelevantSentence = $definition;
    }
}

// Return the most relevant definition as a JSON response
$response = array("sentence" => $mostRelevantSentence);
header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>
