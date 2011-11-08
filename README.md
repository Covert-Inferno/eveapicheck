# EVE API Check

## Introduction

This is a simple tool for checking which kind of information a customizable API key (CAK) offers. Currently it shows:

* Which API functions can be accessed
* Which type the access is (Character, Account or Corporation)
* If the CAK has an expiry date and if yes, when
* Display portrait of accessible characters and their corporations with links to their EVE Gate profiles

## Requirements
* PHP 5.3 with mcrypt and pdo_mysql
* A MySQL database

## Installation

* Move the file to a web host
* Call the install.php in your browser
* Enter your database credentials and press check
* Create a new Login by entering a passphrase into the right field
* Click "Save and proceed" on the bottom
* Confirm
* Log in with the passphrase you entered

If anything goes wrong, API Check notifies you about it. 

## License
Modified BSD License

See the LICENSE.md file for details or go to
http://www.opensource.org/licenses/BSD-3-Clause