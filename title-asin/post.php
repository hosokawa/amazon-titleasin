<?php
namespace Amazon\Title;

class ASIN {

    var $titles;
    public function __construct() {
        
        $ok = false;
        if (isset($_SERVER) && is_array($_SERVER) && array_key_exists('REQUEST_METHOD', $_SERVER) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST) && is_array($_POST) && array_key_exists('titles', $_POST)) {
                $titles = $_POST['titles'];
                $this->titles = [];
                foreach (preg_split('/[\r\n]+/', $titles) as $title) {
                    $title = trim($title);
                    if ($title && !in_array($title, $this->titles)) {
                        $this->titles[] = $title;
                    }
                }
                $ok = true;
            }
        }

        if (!$ok) {
            throw new \Exception('POST??');
        }
    }

    public function getTitles() {
        return $this->titles;
    }

    protected function findTitle($node) {
	$a = [];
        foreach ($node->childNodes as $child) {
	    if ($child->nodeName == "a") {
	        $a[] = trim($child->textContent);
	    } else {
		$title = $this->findTitle($child);
		if ($title) {
	            $a = array_merge($a, $title);
		}
	    }
        }
	return (count($a) == 0) ? false : $a;
    }

    public function searchTitle($title) {
	$best_asin = "";
	$best_sim = 0;
        $doc = new \DOMDocument();
	$prv_use = libxml_use_internal_errors(true);
	$html = file_get_contents('https://www.amazon.co.jp/s?k=' . urlencode($title) . "&amp;__mk_ja_JP=" . urlencode('カタカナ') . "&amp;ref=nb_sb_noss");
	file_put_contents("/tmp/k.html", $html);
        $doc->loadHTML($html);
	libxml_clear_errors();
	libxml_use_internal_errors($prv_use);
	$xpath = new \DOMXPath($doc);
	$nodeList = $xpath->query('//div[@data-asin]');
	foreach ($nodeList as $node) {
	    $asin = trim($node->getAttribute('data-asin'));
	    if ($asin) {
                foreach ($this->findTitle($node) as $compare) {
		    $compare = trim($compare);
		    if ($compare) {
// echo $asin . " " . $compare . "<br/>";
		        similar_text($title, $compare, $sim1);
		        similar_text($compare, $title, $sim2);
		        $sim = $sim1 + $sim2;
		        if ($best_sim < $sim) {
		            $best_sim = $sim;
		            $best_asin = $asin;
		        }
		    }
		}
	    }
	}
	return $best_asin;
    }
}

date_default_timezone_set('Asia/Tokyo');
$d = new \DateTime();
$filename = $d->format('Ymd-His') . ".csv";
header('Content-Type: application/octet-stream');
header("Content-Disposition: attachment; filename={$filename}");
header('Content-Transfer-Encoding: binary');
$fp = fopen('php://output', 'w');
stream_filter_append($fp, 'convert.iconv.UTF-8/CP932//TRANSLIT', STREAM_FILTER_WRITE);
$asin = new ASIN;
foreach ($asin->getTitles() as $title) {
    sleep(10);
    fwrite($fp, $asin->searchTitle($title) . "\t" . $title . "\n");
}
fclose($fp);
