<?php

/*
 * hMailServer MoveSpam for Roundcube
 * Copyright (C) 2023, RvdH
 *
 * This is free and unencumbered software released into the public domain.
 *
 * Anyone is free to copy, modify, publish, use, compile, sell, or
 * distribute this software, either in source code form or as a compiled
 * binary, for any purpose, commercial or non-commercial, and by any
 * means.
 *
 * In jurisdictions that recognize copyright laws, the author or authors
 * of this software dedicate any and all copyright interest in the
 * software to the public domain. We make this dedication for the benefit
 * of the public at large and to the detriment of our heirs and
 * successors. We intend this dedication to be an overt act of
 * relinquishment in perpetuity of all present and future rights to this
 * software under copyright law.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS BE LIABLE FOR ANY CLAIM, DAMAGES OR
 * OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
 * ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 * For more information, please refer to <https://unlicense.org>
 */

class hms_movespam extends rcube_plugin
{
    public $task = "mail";
    private $open_mbox = "INBOX";
    private $spam_mbox = "JUNK";
    private $trash_mbox = "TRASH";
    private $spam_key = "X-hMailServer-Spam";
    private $spam_value = "YES";
    private $move_seen = false;
    private $rc;

    function init()
    {
        $this->rc = rcmail::get_instance();
        $this->load_config();
        $this->spam_mbox = $this->rc->config->get("junk_mbox", null);
        $this->trash_mbox = $this->rc->config->get("trash_mbox", null);
        $this->spam_key = $this->rc->config->get("movespam_key", null);
        $this->spam_value = $this->rc->config->get("movespam_value", null);
        $this->move_seen = $this->rc->config->get("movespam_seen", null);
        if ($this->rc->task == "mail") {
            $this->add_hook("storage_init", [$this, "fetch_headers"]);
            $this->add_hook("messages_list", [$this, "check_headers"]);
        }
    }

    function fetch_headers($fields)
    {
        $key = strtolower($this->spam_key);
        if (!stripos($fields["fetch_headers"], $key)) {
            array_push($fields, $key);
            $fields["fetch_headers"] = trim(
                $fields["fetch_headers"] . " " . strtoupper($key)
            );
        }
        return $fields;
    }

    function check_headers($mlist)
    {
        $imap = $this->rc->storage;
        $this->open_mbox = $imap->get_folder();

        if (
            is_array($mlist["messages"]) &&
            $this->open_mbox != $this->spam_mbox &&
            $this->open_mbox != $this->trash_mbox
        ) {
            $spam_uids = [];

            foreach ($mlist["messages"] as $message) {
                $value = $message->get($this->spam_key);
                if (isset($value)) {
                    if (strpos($value, $this->spam_value) === 0) {
                        if (
                            !isset($message->flags["SEEN"]) ||
                            $this->move_seen
                        ) {
                            $spam_uids[] = $message->uid;
                        }
                    }
                }
            }

            if (count((array) $spam_uids)) {
                $spam_uids_str = implode(",", $spam_uids);
                $imap->move_message(
                    $spam_uids_str,
                    $this->spam_mbox,
                    $this->open_mbox
                );
                $unseen_count = $imap->count(
                    $this->spam_mbox,
                    "UNSEEN",
                    false,
                    false
                );
                $this->rc->output->command(
                    "set_unread_count",
                    $this->spam_mbox,
                    $unseen_count,
                    false
                );
                $this->api->output->command("list_mailbox");
                $this->api->output->send();
                $this->api->output->command("getunread");
                $this->api->output->send();
            }
        }
    }
}
?>