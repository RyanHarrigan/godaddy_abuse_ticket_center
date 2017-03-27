README
# GoDaddy Ticket Abuse Dashboard

## Description
This dashboard lists open and closed tickets that are associated with your GoDaddy Reseller account. You can submit a new tickets, read, and search previous tickets.

---

## Installation
Create a database, user and password for this application. Copy the files to a directory accessible from a URL. Modify the `config.php` file with the database credentials and GoDaddy's supplied API credentials.

### Dependencies
`php-curl` may be needed for successful curl requests.
`sudo apt-get install php7.0-curl`
or
`sudo apt-get install php5-curl`

### Config
The configuration file `config.php` holds variable for the application to connect to a MySQL database, render proper GoDaddy endpoints

---

## How To

### Submit a ticket
The black plus button on the right in the "Open Tickets" header will open a new ticket form. The only rquired field is the Source field, which must be a FULL URL, including http:// or https://.

### Read a ticket
Click on any of the tickets listed in table format. A form with ticket details will show.

### Search for content
This application has a simple search functionality. Any provided search that matches any ticket property or comment will be listed.

---

Made by [Change Programming](https://changeprogramming.com).
