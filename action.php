<?php
/**
 * DokuWiki Plugin autonumbering (Action Component)
 *
 * Adds autonumbering support for DokuWiki headings.
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

// must be run within DokuWiki
if (!defined('DOKU_INC')) die();

class action_plugin_autonumbering extends ActionPlugin
{
    /**
     * @inheritDoc
     */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PARSER_HANDLER_DONE', 'AFTER', $this, 'numberHeaders');
    }

    /**
     * Rebuild autonumbering calls in document order and replace heading markers.
     *
     * DokuWiki headings are parsed as plain header calls, so syntax plugins are
     * not evaluated inside them. Walking the final instruction list keeps heading
     * and body counters in the same order.
     *
     * @param Event $event Event object
     * @param mixed $param optional parameter passed when the event was registered
     * @return void
     */
    public function numberHeaders(Event $event, $param)
    {
        if (!$event->data instanceof Doku_Handler) {
            return;
        }

        global $COUNTER;
        $COUNTER = [];

        foreach ($event->data->calls as &$call) {
            if ($call[0] === 'header') {
                $call[1][0] = $this->replaceHeaderMarkers($call[1][0], $event->data);
                continue;
            }

            if ($call[0] !== 'plugin' || $call[1][0] !== 'autonumbering') {
                continue;
            }

            $syntax = plugin_load('syntax', 'autonumbering');
            if (!$syntax instanceof syntax_plugin_autonumbering) {
                continue;
            }

            $call[1][1] = $syntax->handle($call[1][3], $call[1][2], $call[2], $event->data);
        }
        unset($call);
    }

    /**
     * Replace autonumbering markers in a heading title with plain heading text.
     *
     * @param string $title heading title
     * @param Doku_Handler $handler parser handler
     * @return string
     */
    protected function replaceHeaderMarkers($title, Doku_Handler $handler)
    {
        $syntax = plugin_load('syntax', 'autonumbering');
        if (!$syntax instanceof syntax_plugin_autonumbering) {
            return $title;
        }

        return preg_replace_callback(
            '/~~[a-zA-Z0-9_\.#@]*#[a-zA-Z0-9_\.#@]*~~/',
            function ($matches) use ($syntax, $handler) {
                $data = $syntax->handle($matches[0], DOKU_LEXER_SPECIAL, null, $handler);
                if (empty($data)) {
                    return $matches[0];
                }

                return strip_tags($data[0]);
            },
            $title
        );
    }
}
