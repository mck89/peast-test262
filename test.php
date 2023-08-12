<?php
if (!defined("DS")) {
    define("DS", DIRECTORY_SEPARATOR);
}
include __DIR__ . DS . "vendor" . DS . "autoload.php";

ini_set("xdebug.max_nesting_level", 1000);

class Test262
{
    const COLUMNS = 60;
    
    protected $testPath;
    
    protected $results;
    
    // Array to exclude single test files
    protected $excludedFiles = array(
        // This file includes "new import.meta" syntax, that is semantically correct but never valid at runtime
        // so Peast does not parse it
        "language" . DS . "expressions" . DS . "import.meta" . DS . "import-meta-is-an-ordinary-object.js",
        // Unicode identifiers tests are skipped because they depend on PCRE version
        "language" . DS . "identifiers" . DS . "start-unicode",
        "language" . DS . "identifiers" . DS . "part-unicode",
    );
    
    // Array of unimplemented features that should not be tested
    protected $excludedFeatures = array(
        "import-assertions", "json-modules", "decorators"
    );
    
    public function __construct($testsPath)
    {
        $this->testPath = $testsPath;
        $this->results = (object) array(
            "Total" => 0,
            "Success" => 0,
            "Failed" => 0,
            "TotalTime" => 0,
            "MaxTime" => 0,
            "MaxTimeTest" => null,
            "Errors" => array()
        );
    }
    
    public function run()
    {
        // Iterator that loops all tests file
        $testFilesIterator = new \RegexIterator (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->testPath)
            ),
            "/(?<!_FIXTURE)\.js$/"
        );
        
        foreach ($testFilesIterator as $testFile) {
            // Skip files to exclude
            $skipMatch = str_replace($this->testPath . DS, "", $testFile);
            $skip = false;
            foreach ($this->excludedFiles as $excPattern) {
                if (strpos($skipMatch, $excPattern) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }
            // Run test on current file
            $this->test($testFile->getPathname());
        }
    }
    
    public function test($testFile)
    {
        // Get data and metadata from test file
        list($source, $flags, $features, $expected, $errorPhase) = $this->getTestData($testFile);
        
        // Exclude tests that require unimplemented features and tests that fail
        // on runtime or early error since Peast does not handle them
        $excludedFeatures = array_intersect($this->excludedFeatures, $features);
        if ($excludedFeatures || 
            $errorPhase === "runtime" ||
            $errorPhase === "early" ||
            $errorPhase === "parse" ||
            $errorPhase === "resolution") {
            return;
        }
        
        // Check if it must be parsed as a module
        if (in_array("module", $flags)) {
            $sourceType = \Peast\Peast::SOURCE_TYPE_MODULE;
        } else {
            $sourceType = \Peast\Peast::SOURCE_TYPE_SCRIPT;
        }
        
        // Check if it must be executed in strict mode
        if (in_array("onlyStrict", $flags)) {
            $source = '"use strict"' . "\n" . $source;
        }
        
        $options = array("sourceType"=> $sourceType);
        
        $start = microtime(true);
        
        // Run the test
        $result = null;
        try {
            \Peast\Peast::latest($source, $options)->parse();
        } catch (\Exception $e) {
            $result = $e;
        } catch (\Error $e) {
            $result = $e;
        }
        
        $end = microtime(true);
        
        // Report test result
        $this->report($expected, $result, $testFile, $end - $start);
    }
    
    public function getTestData($testFile)
    {
        $source = file_get_contents($testFile);
        $parts = explode("---*/", $source, 2);
        $header = $parts[0];
        
        if (preg_match("#flags:\s*\[([^\]]+)\]#", $header, $match)) {
            $flags = preg_split("#[\s,]+#", $match[1]);
        } else {
            $flags = array();
        }
        
         if (preg_match("#features:\s*\[([^\]]+)\]#", $header, $match)) {
            $features = preg_split("#[\s,]+#", $match[1]);
        } else {
            $features = array();
        }
        
        $expected = true;
        $errorPhase = null;
        if (preg_match("#type:\s*\w*?Error#", $header)) {
            $expected = false;
            if (preg_match("#phase:\s*(\w+)#", $header, $match)) {
                $errorPhase = $match[1];
            }
        }
        
        return array($source, $flags, $features, $expected, $errorPhase);
    }
    
    public function report($expected, $result, $testFile, $time)
    {
        $error = null;
        if ($expected && $result) {
            $error = "Unexpectd exception: " . $result->getMessage() . "\n" .
                     $result->getTraceAsString();
        } elseif (!$expected && !$result) {
            $error = "Exception not thrown";
        }
        
        $this->results->Total++;
        
        if ($error) {
            $this->results->Failed++;
            $this->results->Errors[] = $testFile . "\n" . $error;
        } else {
            $this->results->Success++;
        }
        
        if ($time > $this->results->MaxTime) {
            $this->results->MaxTime = $time;
            $this->results->MaxTimeTest = $testFile;
        }
        
        $this->results->TotalTime += $time;
        
        echo $error ? "F" : ".";
        if ($this->results->Total % self::COLUMNS === 0) {
            echo " " . $this->results->Total;
            echo "\n";
        }
    }
    
    public function printReport()
    {
        echo "\n\n";
        echo implode(", ", array(
            "Total: " . $this->results->Total,
            "Success: " . $this->results->Success,
            "Failed: ".  $this->results->Failed,
        ));
        echo "\n\n";
        echo "Total time: " . round($this->results->TotalTime, 2) . "s";
        echo "\n\n";
        echo "Longest parsing time (" . round($this->results->MaxTime * 1000, 2) . "ms): " .
             $this->results->MaxTimeTest;
        echo "\n\n";
        if ($this->results->Errors) {
            echo "Errors";
            foreach ($this->results->Errors as $error) {
                echo "\n\n";
                echo str_repeat("-", self::COLUMNS);
                echo "\n\n";
                echo $error;
            }
            echo "\n";
        }
    }
}

$testsPath = __DIR__ . DS . "test262". DS . "test";

$runner = new Test262($testsPath);
$runner->run();
$runner->printReport();
