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

        while ($relatedRow = $relatedResult->fetch_assoc()) {
            $relatedWord = $relatedRow['word'];
            $relatedDefinition = $relatedRow['definition'];
            $similarityScore = calculateSimilarity($definition, $relatedDefinition);

            // If the similarity score is above a threshold, consider it a related word
            if ($similarityScore > 69) {
                $relatedWords[] = $relatedWord;
            }
        }
//eorror correction after a=ab a=b a=b+-.x a=~b ab=b+-a b=ab+-.x*ab ab=/=a a=b
        // Update the word's related_word column with the related words
        $relatedWordsString = implode(',', $relatedWords);
        $updateQuery = "UPDATE word SET related_word = ? WHERE word = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('ss', $relatedWordsString, $word);
        $updateStmt->execute();
    }
    echo "Related words updated for all entries in the word table.";
} else {
    echo "No words found in the word table.";
}

$conn->close();
?>