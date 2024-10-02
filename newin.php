<?php
// File: analyze_words.php
session_start();

// Database connection details
$servername = "localhost";
$username = "root";
$password = ""; // Use your MySQL password
$dbname = "reservesphp";

// Create a connection to the MySQL database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection is established
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Helper function to calculate similarity between two strings
function calculateSimilarity($str1, $str2) {
    similar_text($str1, $str2, $percent);
    return $percent;
}

// Iterate through each word and compare its definition against all others
$query = "SELECT word, definition FROM word";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($wordRow = $result->fetch_assoc()) {
        $word = $wordRow['word'];
        $definition = $wordRow['definition'];

        // Find related words based on definition similarity
        $relatedWords = [];
        $definitionQuery = "SELECT word, definition FROM word WHERE word != ?";
        $stmt = $conn->prepare($definitionQuery);
        $stmt->bind_param('s', $word);
        $stmt->execute();
        $relatedResult = $stmt->get_result();

        // Store related words with their similarity scores
        $relatedWordsWithScores = [];

        while ($relatedRow = $relatedResult->fetch_assoc()) {
            $relatedWord = $relatedRow['word'];
            $relatedDefinition = $relatedRow['definition'];
            $similarityScore = calculateSimilarity($definition, $relatedDefinition);

            // Only consider related words with a similarity score of 90% or more
            if ($similarityScore >= 90) {
                $relatedWordsWithScores[] = ['word' => $relatedWord, 'score' => $similarityScore];
            }
        }

        // Sort related words by their similarity score in descending order
        usort($relatedWordsWithScores, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Select the top 3 most similar words
        $topRelatedWords = array_slice(array_column($relatedWordsWithScores, 'word'), 0, 3);

        // Convert the array of related words to a comma-separated string
        $relatedWordsString = implode(',', $topRelatedWords);

        // Update the word's related_word column, appending the new related words with a comma
        $updateQuery = "UPDATE word SET related_word = CONCAT(IFNULL(related_word, ''), CASE WHEN related_word = '' OR related_word IS NULL THEN ? ELSE CONCAT(',', ?) END) WHERE word = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('sss', $relatedWordsString, $relatedWordsString, $word);
        $updateStmt->execute();
    }
    echo "Related words updated for all entries in the word table.";
} else {
    echo "No words found in the word table.";
}

$conn->close();
?>
