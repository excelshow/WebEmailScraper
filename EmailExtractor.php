<?php
ini_set('xdebug.max_nesting_level', 500); // Allows for 500 recursive calls
ini_set('max_execution_time', 900); // Allows script to run for 15 minutes


class EmailScraper { 

    var $originalURL;
    var $startURL;
    var $startPath; // Start path, for links that are relative 
    var $disallowedURLPatterns = array('tag', 'category', 'xmlrpc', '.xml', '.rss', 'feed', '.js', '?', '#', '.jpg', '.png', '.gif'); 
    var $disallowedEmailPatterns = array('wordpress', 'jquery', '.jpg', '.png', '.gif');
    var $emailsFound = array();
    var $urlsSearched = array();
    var $urlsToBeSearched = array();


    function setOriginalURL($url) {
        $this->originalURL = $url; 
    }
    
    function setStartURL($url) { 
        $this->startURL = $url; 
    } 


    function setStartPath() { 
        $temp = explode('/', $this->startURL); 

        if(sizeof($temp) > 1) {
            $this->startPath = $temp[0] . '//' . $temp[2]; 
        }
        else {
            $this->startPath = $temp[0] . '//';
        }
    } 

    
    function startScraping() { 

        if (!in_array($this->startURL, $this->urlsSearched)) {
            echo '<br>Scraping URL: ' . $this->startURL; 

            array_push($this->urlsSearched, $this->startURL);

            $pageContent = $this->getEmails();            
            mysql_query("INSERT INTO searchedUrls (url) VALUES ('" . $this->startURL . "')"); 
            $this->getMoreUrls($pageContent);
        }

        $this->getNewPageReady();

        if($this->startURL != NULL){
            $this->startScraping(); 
        }
    } 


    /*
    * Get emails on current page.
    */
    function getEmails() {
        $pageContent = $this->getURLContents($this->startURL); 
        preg_match_all('/([\w+\.]*\w+@[\w+\.]*\w+[\w+\-\w+]*\.\w+)/is', $pageContent, $results); // Get list of all emails on page 

        foreach($results[1] as $email) { 
            str_replace($this->disallowedEmailPatterns, '', $email, $count); // Disallow certain email patterns
            if($count == 0) { 
                if (!in_array($email, $this->emailsFound)) { // If email not added already
                    mysql_query("INSERT INTO emails (address, fromUrl) VALUES ('$email', '" . $this->startURL . "')"); 
                    array_push($this->emailsFound, $email);
                    // echo '<br>Email found: ' . $email; 
                }
            }
        } 

        return $pageContent;
    }


    /*
    * Get more urls that need to be searched.
    */
    function getMoreUrls($pageContent) {
        preg_match_all('/href="([^"]+)"/Umis', $pageContent, $results); 
        $urls = $this->removeUnwantedUrls($results[1]); 

        foreach($urls as $url) { 
            if (!in_array($url, $this->urlsToBeSearched)) { // If url not added already
                mysql_query("INSERT INTO urlsToBeSearched (url) VALUES ('$url')"); 
                array_push($this->urlsToBeSearched, $url);
            }
        }

        unset($results, $pageContent);
    }


    /*
    * Get new page ready to scrape for emails.
    */
    function getNewPageReady() {
        $getURL = mysql_fetch_assoc(mysql_query("SELECT url FROM urlsToBeSearched ORDER BY RAND() LIMIT 1")); 
        mysql_query("DELETE FROM urlsToBeSearched WHERE url='$getURL[url]' LIMIT 1"); 
        
        $this->startURL = $getURL['url']; 
        $this->setStartPath(); 
    }
    

    /*
    * Remove unwanted URLs.
    */
    function removeUnwantedUrls($urls) {
        foreach($urls as $key => $url) { 

            // Remove urls with invalid extensions 
            str_replace($this->disallowedURLPatterns, '', $url, $count); 
            if($count > 0) { 
                // echo "<br>Disallowed url: " . $url; 
                unset($urls[$key]);
            } 

            // Remove URLs that are not subdomains of the original website
            // or do not begin with '/' 
            if(substr($url, 0, 1) == "/") { }
            else if(strpos($url, $this->originalURL) === false) { 
                // echo "<br>Unsetting: " . $url;
                unset($urls[$key]);
            } 

            // If everything is OK and path is relative, add starting path 
            if(substr($url, 0, 1) == '/' || substr($url, 0, 1) == '?' || substr($url, 0, 1) == '='){ 
                $urls[$key] = $this->startPath . $url; 
            } 
        } 

        return $urls; 
    } 
    

    function getURLContents($url) { 
        $cURL = curl_init();
        curl_setopt($cURL, CURLOPT_HEADER, 0); 
        curl_setopt($cURL, CURLOPT_VERBOSE, 0); 
        curl_setopt($cURL, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)"); 
        curl_setopt($cURL, CURLOPT_AUTOREFERER, false); 
        curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 7); 
        curl_setopt($cURL, CURLOPT_URL, $url); // set url to post to 
        curl_setopt($cURL, CURLOPT_FAILONERROR, 1); 
        curl_setopt($cURL, CURLOPT_FOLLOWLOCATION, 1); // allow redirects 
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, 1); // return into a variable 
        curl_setopt($cURL, CURLOPT_TIMEOUT, 50); // time out after 50s 
        curl_setopt($cURL, CURLOPT_POST, 0); // set POST method 
        $buffer = curl_exec($cURL);
        curl_close($cURL); 
        return $buffer; 
    } 
} 
?> 
