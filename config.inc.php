<?php

// Name of the header to inspect.
$rcmail_config['movespam_key'] = 'X-hMailServer-Spam';

// If this string is at the beginning of the header, the message will be moved to the junk folder.
$rcmail_config['movespam_value'] = 'YES';

// True to move all spam. False to move only new (unread) spam.
$rcmail_config['movespam_seen'] = false;

?>