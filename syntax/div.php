<?php
/**
 * Folded text Plugin: enables folded text font size with syntax ++ text ++
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Fabian van-de-l_Isle <webmaster [at] lajzar [dot] co [dot] uk>
 * @author     Christopher Smith <chris@jalakai.co.uk>
 * @author     Esther Brunner <esther@kaffeehaus.ch>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

// maintain a global count of the number of folded elements in the page, 
// this allows each to be uniquely identified
global $plugin_folded_count;
if (!isset($plugin_folded_count)) $plugin_folded_count = 0;

// global used to indicate that the localised folder link title tooltips 
// strings have been written out
global $plugin_folded_strings_set;
if (!isset($plugin_folded_string_set)) $plugin_folded_string_set = false;

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_folded_div extends DokuWiki_Syntax_Plugin {

   /**
    * return some info
    */
    function getInfo(){
        return array(
            'author' => 'Fabian van-de-l_Isle',
            'email'  => 'webmaster@lajzar.co.uk',
            'date'   => '2008-08-13',
            'name'   => 'Folded Plugin - Block',
            'desc'   => 'Enable blocks of folded text. 
                         Syntax: ++++title|folded content++++',
            'url'    => 'http://www.dokuwiki.org/plugin:folded',
        );
    }

    function getType(){ return 'container'; }
    function getPType() { return 'block'; }
    function getAllowedTypes() { return array('container','substition','protected','disabled','formatting'); }
    function getSort(){ return 404; }
    function connectTo($mode) { $this->Lexer->addEntryPattern('\+\+\+\+.*?\|(?=.*\+\+\+\+)',$mode,'plugin_folded_div'); }
    function postConnect() { $this->Lexer->addExitPattern('\+\+\+\+','plugin_folded_div'); }

   /**
    * Handle the match
    */
    function handle($match, $state, $pos, &$handler){
        if ($state == DOKU_LEXER_ENTER){
            $match = trim(substr($match,4,-1)); // strip markup
            $handler->status['plugin_folded'] = true;

            if (!$this->register_hook) {

              global $EVENT_HANDLER;
              $EVENT_HANDLER->register_hook('PARSER_HANDLER_DONE','BEFORE', $this, 'add_writestrings');

              $this->register_hook = true;
            }
        } else if ($state == DOKU_LEXER_UNMATCHED) {
            $handler->_addCall('cdata',array($match), $pos);
            return false;
        }
        return array($state, $match);
    }

   /**
    * Create output
    */
    function render($mode, &$renderer, $data) {
        global $plugin_folded_count;

        if (empty($data)) return false;
        list($state, $cdata) = $data;

        if($mode == 'xhtml') {
            switch ($state){
              case DOKU_LEXER_ENTER:
                $plugin_folded_count++;
                $renderer->doc .= '<p><a class="folder" href="#folded_'.$plugin_folded_count.'">';

                if ($cdata)
                    $renderer->doc .= ' '.$renderer->_xmlEntities($cdata);

                $renderer->doc .= '</a></p><div class="folded hidden" id="folded_'.$plugin_folded_count.'">';
                break;

              case DOKU_LEXER_UNMATCHED:                            // defensive, shouldn't occur
                $renderer->cdata($cdata);
                break;

              case DOKU_LEXER_EXIT:
                $renderer->doc .= '</div>';
                break;

              case 'WRITE_STRINGS' :
                if (!$plugin_folded_strings_set) {

                  $hide = $this->getConf('hide') ? $this->getConf('hide') : $this->getLang('hide');
                  $reveal = $this->getConf('reveal') ? $this->getConf('reveal') : $this->getLang('reveal');

                  $renderer->doc .= '<span id="folded_reveal" style="display:none;"><!-- '.hsc($reveal).' --></span>';
                  $renderer->doc .= '<span id="folded_hide" style="display:none;"><!-- '.hsc($hide).' --></span>';

                  $plugin_folded_strings_set = true;
                }
            }
            return true;
        } else {
            //  handle unknown formats generically - by calling standard render methods
            switch ( $state ) {
               case DOKU_LEXER_ENTER: 
                $renderer->p_open();
                $renderer->cdata($cdata);
                $renderer->p_close();
                $renderer->p_open();
                break;
              case DOKU_LEXER_UNMATCHED:                            // defensive, shouldn't occur
                $renderer->cdata($cdata);
                break;
              case DOKU_LEXER_EXIT:
                $renderer->p_close();
                break;
            }
        }
        return false;
    }

    function add_writestrings(&$event, $param) {

      if (isset($event->plugin_folded)) return;

      // make sure the event is being generated for the handler instance we expect
      $handler =& $event->data;
      if (empty($handler->status['plugin_folded'])) return;

      // add WRITE_STRINGS instruction to the end of the instruction list
      $last_call = end($handler->calls);
      array_push($handler->calls, array('plugin', array('folded_span', array('WRITE_STRINGS',0), DOKU_LEXER_MATCHED), $last_call[2]));

      // prevent multiple handling of this event by folded plugin components
      $event->plugin_folded = true;
    }
}