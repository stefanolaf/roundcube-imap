# roundcube-imap

A PHP IMAP library to read and process e-mails over IMAP protocol. It works independently of PHP IMAP extension and supports newer additions to the IMAP protocol like CONDSTORE and QRESYNC which is not supported by the standard PHP IMAP extension.

The code is nearly entirely extracted from the Roundcubemail project.

## Installation

The recommended way to install this package is through [Composer](https://getcomposer.org). Please note that this package is only available in Composer 2. If you still run Composer 1 you will have to update to Composer 2.

```bash
$ composer require bjc/roundcube-imap
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

## Usage

### Connect and Authenticate

```php

// $ssl_mode is optional. It can take values "tls", "ssl" and "plain". Default value is "tls".
$server = new \bjc\roundcubeimap\server($host, $ssl_mode);

// $connection is an object of \bjc\roundcubeimap\connection.
$connection = $server->authenticate($username, $password);
```


### Mailboxes

Retrieve mailboxes (also known as mail folders) from the mail server and iterate over them:

```php
$mailboxes = $connection->getMailboxes();

foreach ($mailboxes as $mailbox) {

  // $mailbox is an object of \bjc\roundcubeimap\mailbox

}

```

Or retrieve a specific mailbox:

```php
$mailbox = $connection->getMailbox('INBOX');
```

Delete a mailbox:

```php
$connection->deleteMailbox($mailbox);
```

#### Status of a mailbox

To retrieve the status of a mailbox run:

```php
$status = $mailbox->getStatus();
// $status is a stdClass object that contains the values uidnext, uidvalidity, recent and if available highestmodseq
```

### Messages

For retrieving messages it is important to understand the concept of UIDs and uidvalidity. UID is a numeric unique identifier of a message WITHIN the mailbox (not to confuse with the message ID which is an alphanumeric identifier that identifies a message "world wide"). When a new message is being put into a mailbox, it will always get a higher UID than all the messages before. As a result, when your application knows which UID it fetched last, it can easily ask the IMAP server to just send the messages that were newly received. This concept works as long as the UIDs of messages don't change and new messages always get higher UIDs than existing ones. There are scenarios were this is not the case and this is the reason for uidvalidity. Uidvalidity is a parameter maintained by the IMAP server for each mailbox. As long as this parameter stays the same, the UIDs assigned to the existing mails did not change and new messages always received higher UIDs than the ones that existed.

Therefore your application has to store two parameters for each mailbox:
- uidvalidity
- last fetched uid

If you check the mailbox, check first whether uidvalidity changed compared to which uidvalidity you have stored in your application for the respective mailbox. If it changed, delete the stored email data in your application (for this mailbox) and fetch everything again as UIDs for messages could have changed. If uidvalidity stayed the same, then just fetch the new messages higher than your last fetched uid.

To get the current uidvalidity value run:

```php
$status = $mailbox->getStatus();
$uidvalidity = $status->uidvalidity;
```


#### Get messages higher than a certain UID (normally the one you fetched at last)

```php
$messagearray = $mailbox->getMessageshigherthan($lastfetcheduid);

foreach ($messagearray as $message) {
  // $message is an object of \bjc\roundcubeimap\message
}

```

If you plan to analyse the bodystructure of all e-mails you already can request the bodystructure to be fetched (and stored in the message object) for all messages in the set by setting the second variable of the function call to true:
```php
$messagearray = $mailbox->getMessageshigherthan($lastfetcheduid, true);
```
Anyway the message object will take care of fetching the bodystructure later if needed but not yet stored in the message object.

Note that the above command will fetch a standard set of headers of the selected messages. You can add additional headers you would like to fetch by adding an array of strings as a third variable to the function call:
```php
$messagearray = $mailbox->getMessageshigherthan($lastfetcheduid, false, ['X-Spam-Status','X-Mailer']);
```

#### Get messages of a certain message set (e.g. if you want to fetch all messages again because uidvalidity changed)

```php

$message_set = '1:*';

$messagearray = $mailbox->getMessageSequence($message_set);

foreach ($messagearray as $message) {
  // $message is an object of \bjc\roundcubeimap\message
}

```
If you plan to analyse the bodystructure of all e-mails you already can request the bodystructure to be fetched (and stored in the message object) for all messages in the set by setting the second variable of the function call to true:
```php
$messagearray = $mailbox->getMessageSequence($message_set, true);
```
Anyway the message object will take care of fetching the bodystructure later if needed but not yet stored in the message object.

Note that the above command will fetch a standard set of headers of the selected messages. You can add additional headers you would like to fetch by adding an array of strings as a third variable to the function call:
```php
$messagearray = $mailbox->getMessageSequence($message_set, false, ['X-Spam-Status','X-Mailer']);
```

#### If you know the UID of the message you want to retrieve

```php
$message = $mailbox->getMessage($uid);
// $message is an object of \bjc\roundcubeimap\message
```

If you plan to analyse the bodystructure of the e-mail you already can request the bodystructure to be fetched (and stored in the message object) by setting the second variable of the function call to true:
```php
$messagearray = $mailbox->getMessage($uid, true);
```
Anyway the message object will take care of fetching the bodystructure later if needed but not yet stored in the message object.

Note that the above command will fetch a standard set of headers of the selected message. You can add additional headers you would like to fetch by adding an array of strings as a third variable to the function call:
```php
$message = $mailbox->getMessage($uid, false, ['X-Spam-Status','X-Mailer']);
```

### Checking whether your server is capable of synchronization (i.e. has CONDSTORE and QRESYNC extension implemented)

Synchronization will help you to keep your applications data up to date with what is going on on the IMAP server. First you have to check if your server supports it:

```php
$condstore = $mailbox->checkCondstore();
// true if condstore is available, false if not

$qresync = $mailbox->checkQresync();
// true if qresync is available, false if not
```


#### If your server supports CONDSTORE and QRESYNC extension then you can synchronize your data

'Synchronize' means you can get just the messages that changed, came in new or got deleted since your last fetch.
For this you have to store another parameter (beside uidvalidity) for each mailbox in your persistence (e.g. your database):
Highestmodsequence

Highestmodsequence is a numeric value that increases each time something changed in the mailbox. The IMAP server keeps track of which changes happened at which modsequence and therefore can tell which changes it needs to tell you when you ask for changes since a certain modsequence.

To get the current highestmodseq for a mailbox (in order to store it in your application until your next fetch) run:

```php
$status = $mailbox->getStatus();
$highestmodseq = $status->highestmodseq;
```

To get the changes since last synchronization run:

```php
$synchronize_result = $mailbox->synchronize($stored_highestmodseq, $stored_uidvalidity);

// check if status == 1 (query worked), if status == 0 check statusmessage to see what went wrong:
$status = $synchronize_result["status"];
$statusmessage = $synchronize_result["statusmesage"];

// Get array of messages
$messagearray = $synchronize_result["messagearray"];

foreach ($messagearray as $message) {
  // $message is an object of \bjc\roundcubeimap\message
}

// get array of vanished (deleted) messages
$vanishedarray = $synchronize_result["vanishedarray"];

foreach ($vanishedarray as $uid) {
  // uid is the uid of the deleted message
}

```

If you plan to analyse the bodystructure of the e-mails you already can request the bodystructure to be fetched (and stored in the message object) by setting the third variable of the function call to true:
```php
$messagearray = $mailbox->synchronize($stored_highestmodseq, $stored_uidvalidity, true);
```
Anyway the message object will take care of fetching the bodystructure later if needed but not yet stored in the message object.


Note that the above command will fetch a standard set of headers of the selected message. You can add additional headers you would like to fetch by adding an array of strings as a fourth variable to the function call:
```php
$synchronize_result = $mailbox->synchronize($stored_highestmodseq, $stored_uidvalidity, false, ['X-Spam-Status','X-Mailer']);
```


#### If your server does not support CONDSTORE and QRESYNC

If your server does not support CONDSTORE and QRESYNC then you have to fetch at least the flags and UIDs of all messages in the mailbox (from time to time or always) to keep track of flag changes and deleted messages.
To keep traffic as low as possible you should run two queries, one for new messages where you fetch all the headers you need and one query where you will only ask for uid, message-id and flags of the old messages.
Use your stored last fetched uid value to distinct between new and old messages (again as long as uidvalidity did not change).

```php

// retrieve only flags, uid and message-id of messages (to update their status).
// All messages with a uid lower than $lastfetcheduid that are known to your application but aren't in the result set of this function have been deleted from the mailbox

$messagearray = $mailbox->getMessageupdate($lastfetcheduid);

foreach ($messagearray as $message) {
  // $message is an object of \bjc\roundcubeimap\message
}
```

Note that the $message object and it's child object $messageheaders will not have the standard set of headers. By default only $uid, $id and the flags will be available.
If you need still more headers you can add them by giving a second argument to the function call:

```php
$messagearray = $mailbox->getMessageupdate($lastfetcheduid, false, ['subject', 'date']);
```

After the update query, run the query for new messages:
```php
$messagearray = $mailbox->getMessageshigherthan($lastfetcheduid);

foreach ($messagearray as $message) {
  // $message is an object of \bjc\roundcubeimap\message
}
```


### Messageheaders

To retrieve headers from the message object, you have the following options:

```php
// message identification
$uid = $message->getUID(); // UID of message
$id = $message->getID(); // globally unique alphanumeric message ID

// Date and subject
$date = $message->getDate); // A datetime object
$timestamp = $message->getTimestamp(); // The timestamp of the date
$subject = $message->getSubject(); // string

// Addresses
$from = $message->getFrom(); // $from is an object of \bjc\roundcubeimap\emailaddress
$to = $message->getTo(); // $to is an array of objects of \bjc\roundcubeimap\emailaddress
$cc = $message->getCC(); // $cc is an array of objects of \bjc\roundcubeimap\emailaddress

// Flags
$isAnswered = $message->isAnswered; // true if ANSWERED flag is set, otherwise false
$isDeleted = $message->isDeleted; // true if DELETED flag is set, otherwise false
$isDraft = $message->isDraft; // true if DRAFT flag is set, otherwise false
$isSeen = $message->isAnswered; // true if SEEN flag is set, otherwise false
$isFlagged = $message->isFlagged; // true if FLAGGED flag is set, otherwise false

// get a specific header
$value = $message->getheader($headername);

// get all headers
$headerarray = $message->getHeaders();

```


#### Email address object

For the emailaddress object you can do the following:

```php
// example: Guybrush Threepwood <guy@mightypirates.com>
$address = $from->getAddress(); // guy@mightypirates.com
$mailbox = $from->getMailbox(); // guy
$hostname = $from->getHostname(); // mightypirates.com
$name = $from->getName(); // Guybrush Threepwood

$fulladdress = $from->getFulladdress; // Guybrush Threepwood <guy@mightypirates.com>
```


## To Do:

* Add retrieval of bodystructure and attachments
* Add choice whether retrieve bodystructure and attachments at time of fetching headers or only if body is requested from the message object
* Add possibility to add messages to folders
* Add delete command
