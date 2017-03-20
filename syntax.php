<?php
/**
 * DokuWiki Plugin autonumbering (Syntax Component)
 *
 * @description         :   This plugin allows the use of multiples counters
 *                          with multiples levels, within the same page.
 *
 * @syntax (Base)       :   ~~#~~
 *                              --> Where ~~#~~ will be replaced by a number,
 *                                  auto incremented, and saved in a common
 *                                  counter.
 * @syntax (ID)         :   ~~#@COUNTERID~~
 *                              --> Where COUNTERID is an alphanumeric
 *                                  identificator, including unserscore,
 *                                  and starting with a @. This allows
 *                                  the use of multiple counters.
 * @syntax (forced)     :   ~~#NUM~~
 *                              --> Where NUM is a positive number that will
 *                                  be the begining of the auto incrementation
 *                                  from there.
 * @syntax (multilevel) :   ~~#.#~~
 *                              --> Where .# represent a sublevel and can be
 *                                  repeated as much as needed.
 *
 * @syntax (text)       :   ~~REPORT.EXAMPLE.#~~
 *                              --> Where only the third level will be an auto
 *                                  incremented number. The first level will
 *                                  be a repeated text. Here it will be REPORT.
 *                                  Samething for the second level with EXAMPLE.
 *                                  When using text in a level, it will be
 *                                  implicitly used as counter ID if no counter
 *                                  ID have been set with @COUNTERID.
 *
 * @example             :   ~~Test.#4.#6@CTR_ONE~~
 *                              --> Where the number will have three levels.
 *                                  First level will be the text « Test ».
 *                                  Second level will be an auto incremented
 *                                  number starting at 4. Third level will be
 *                                  an auto incremented number starting at 6.
 *                                  All this will be save in the counter
 *                                  « CTR_ONE ».
 *
 * @license             :   GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author              :   Patrice Bonneau <patrice.bonneau@live.ca>, Florian Wehner <florian@whnr.de>
 * @lastupdate          :   2017-03-20
 * @compatible          :   2017-02-19 "Frusterick Manners"
 */


// must be run within DokuWiki
if (!defined('DOKU_INC')) die();

if (!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

require_once(DOKU_PLUGIN.'syntax.php');

class syntax_plugin_autonumbering extends DokuWiki_Syntax_Plugin {

    var $PLUGIN_PATTERN = "~~[a-zA-Z0-9_\.#@]*#[a-zA-Z0-9_\.#@]*~~";
    var $NUMBER_PATTERN = "[0-9]+";
    var $COUNTER_ID_PATTERN = "[a-zA-Z0-9_]+";


    public function getType() {
        return 'substition';
    } 
    
    public function getPType() {
        return 'normal';
    }

    public function getSort() {
        return 45;
    }

    public function getInfo() {
        return array(
            'author' => 'Patrice Bonneau',
            'email'  => 'patrice.bonneau@live.ca',
            'date'   => '2017-03-20',
            'name'   => 'Autonumbering Plugin',
            'desc'   => 'Allows the use of multiples counters with multiples levels, within the same page.',
            'url'    => 'http://www.dokuwiki.org/plugin:autonumbering',
        );
    }


    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern($this->PLUGIN_PATTERN, $mode, 'plugin_autonumbering');
    }


    public function handle($match, $state, $pos, &$handler){
        global $COUNTER;
        $counterID = '';
        switch ($state) {
            case DOKU_LEXER_SPECIAL :

                if (preg_match('/~~(.*?)~~/', $match, $matches)) {
                    $data = $matches[1];

                    if (!empty($data)) {
                        // Search for EXPLICIT counter ID
                        if (preg_match('/@(' . $this->COUNTER_ID_PATTERN . ')/', $data, $matches)) {
                            $counterID = $matches[1];
                            // Remove counter ID from $data
                            $data = str_replace('@' . $counterID, '', $data);
                        } else {    // Search for IMPLICIT counter ID
                            $alpha = preg_replace('/[^a-zA-Z]/', '', $data);
                            if (!empty($alpha))
                                $counterID = $alpha;
                        }

                        // Separate levels
                        $dataTab = explode('.', $data);

                        // Get levels quantity
                        $levelsQty = count($dataTab);
                        $currentLevel = $levelsQty - 1;

                        // Check if parent level exist
                        for ($i = 0; $i < $levelsQty; ++$i) {
                            // Check if level contain text
                            if (ctype_alpha($dataTab[$i]))
                                $COUNTER[$counterID][$i] = $dataTab[$i];
                            // Search for a forced value
                            else if (preg_match('/(' . $this->NUMBER_PATTERN . ')/', $dataTab[$i], $matches))
                                if ($i == $currentLevel)
                                    $COUNTER[$counterID][$i] = $matches[1]-1;
                                else
                                    $COUNTER[$counterID][$i] = $matches[1];
                            // initialize if needed
                            else if ((!isset($COUNTER[$counterID][$i])) || ($COUNTER[$counterID][$i] == 0))
                                if ($i == $currentLevel)
                                    $COUNTER[$counterID][$i] = 0;
                                else
                                    $COUNTER[$counterID][$i] = 1;
                        }

                        // Check if child level exist, and initialize
                        $counter_levelsQty = count($COUNTER[$counterID]);
                        for ($i = $currentLevel+1; $i < $counter_levelsQty; ++$i)
                            $COUNTER[$counterID][$i] = 0;

                        // Increment current level
                        ++$COUNTER[$counterID][$currentLevel];

                        // Return the number, according the level asked
                        $number = '';
                        $period = '';
                        for ($i = 0; $i < $levelsQty; ++$i) {
                            $number .= $period . $COUNTER[$counterID][$i];
                            $period = '.';
                        }
                        return array($number, NULL);
                    } else {
                        return array($match, NULL);
                    }
                }
            break;
        }
        return array();
    }

    public function render($mode, &$renderer, $data) {
        if(($mode == 'xhtml') && (!empty($data))) {
            list($number, $null) = $data;
            $renderer->doc .= $number;
            return true;
        }
        return false;
    }


    // To work with the plugin « reproduce », who needs to do
    // the numbering prior to reproducing the code.
    public function doNumbering($pageContent){
        $qtyOccurrences = preg_match_all('/~~(.*?)~~/', $pageContent, $matches);
        if ($qtyOccurrences > 0) {
            for ($i = 0; $i < $qtyOccurrences; $i++) {
                list($number, $null) = $this->handle($matches[0][$i], DOKU_LEXER_SPECIAL, NULL, $handler);
                $pageContent = preg_replace('(' . $matches[0][$i] . ')', $number, $pageContent, 1);
            }
        }
        return $pageContent;
    }
}
