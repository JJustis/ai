<?php
// Database connection code using PDO
$host = 'localhost';
$db = 'reservesphp'; // Change to your database name
$user = 'root';      // Change to your MySQL username
$pass = '';          // Change to your MySQL password
$charset = 'utf8mb4';

// Setting up the DSN and options for the PDO connection
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Create the PDO instance
try {
    $dbh = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Function to get the most similar definition from the 'word' table based on user input
function getMostSimilarDefinition($dbh, $userInput) {
    $definitions = [];
    $query = "SELECT word, definition FROM word";
    foreach ($dbh->query($query) as $row) {
        $definitions[] = [
            'word' => $row['word'],
            'definition' => $row['definition']
        ];
    }
    
    $maxSimilarity = 0;
    $bestDefinition = '';
    
    // Calculate cosine similarity between user input and each definition
    foreach ($definitions as $definition) {
        $similarity = cosineSimilarity($userInput, $definition['definition']);
        if ($similarity > $maxSimilarity) {
            $maxSimilarity = $similarity;
            $bestDefinition = $definition['definition'];
        }
    }
    
    return [$bestDefinition, $maxSimilarity];
}

// Function to calculate cosine similarity between two sentences
function cosineSimilarity($sentence1, $sentence2) {
    $vector1 = getWordVector($sentence1);
    $vector2 = getWordVector($sentence2);
    $uniqueWords = array_unique(array_merge(array_keys($vector1), array_keys($vector2)));
    $v1 = array_map(function($word) use ($vector1) { return isset($vector1[$word]) ? $vector1[$word] : 0; }, $uniqueWords);
    $v2 = array_map(function($word) use ($vector2) { return isset($vector2[$word]) ? $vector2[$word] : 0; }, $uniqueWords);

    $dotProduct = array_sum(array_map(function($val1, $val2) { return $val1 * $val2; }, $v1, $v2));
    $magnitude1 = sqrt(array_sum(array_map(function($val) { return $val * $val; }, $v1)));
    $magnitude2 = sqrt(array_sum(array_map(function($val) { return $val * $val; }, $v2)));

    return $dotProduct / ($magnitude1 * $magnitude2);
}

// Function to convert a sentence into a word frequency vector
function getWordVector($sentence) {
    $words = explode(' ', strtolower($sentence));
    $wordCount = array();
    foreach ($words as $word) {
        $wordCount[$word] = (isset($wordCount[$word]) ? $wordCount[$word] : 0) + 1;
    }
    return $wordCount;
}

// Function to process all sentences in the 'sentance' table
function processAllSentences($dbh) {
    $query = "SELECT sentance FROM sentances"; // Get all sentences from the 'sentance' table
    $sentences = $dbh->query($query)->fetchAll(PDO::FETCH_COLUMN);

    foreach ($sentences as $userInput) {
        // Get the most similar definition and its similarity score
        list($bestDefinition, $similarity) = getMostSimilarDefinition($dbh, $userInput);
        
        // Log the result into logc.txt
        $logEntry = "$userInput = $bestDefinition | (ab)=($userInput error correction) == (ab)=($bestDefinition error correction) | Cosine Similarity: $similarity\n";
        file_put_contents("logc.txt", $logEntry, FILE_APPEND | LOCK_EX);
        
        // Output the result to the console or to the web page
        echo "<h3>Processed Sentence:</h3>";
        echo "<p><strong>Input Sentence:</strong> $userInput</p>";
        echo "<p><strong>Best Definition:</strong> $bestDefinition</p>";
        echo "<p><strong>Similarity Score:</strong> $similarity</p><hr>";
    }
}

// Run the processing function to handle all sentences in the 'sentance' table
processAllSentences($dbh);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Learning System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: #ffffff;
            padding: 20px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        h1 {
            font-size: 24px;
            color: #343a40;
        }
        h3 {
            font-size: 18px;
            color: #007bff;
        }
        p {
            font-size: 14px;
            color: #343a40;
        }
        hr {
            border: 1px solid #f1f1f1;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>AI Learning System</h1>
        <h2>Automated Sentence Processing</h2>
        <p>This page will automatically process all sentences in the 'sentance' table and log the results in logc.txt.</p>
    </div>
</body>
</html>
