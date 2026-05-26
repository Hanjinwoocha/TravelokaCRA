FOR SYSARCH SUBMISSION:
A project made by the group: TheseAbilities G1

Members:
- Heath Mattheu Pono
- Khen Eruela
- Fitzgerald Amancio
- Rodel Revelo
- Jay Clief Limpag

Techstack:
- PHP
- Bootstrap
- Firebase Firestore (Removed config file cuz api keys)
- XAMPP (Localhost - enable Apache)

How to make the system work:
  1.) Setup Firebase Firestore
  2.) Make php file (as titled below)
  3.) Place the php file inside the includes folder along with the firebase.php file

File Name: firebase_config.php
File Contents:
<?php
/**
 * Firebase configuration — fill in your project values before running.
 *
 * 1. FIREBASE_PROJECT_ID  → Firebase console → Project settings → Project ID
 * 2. FIREBASE_API_KEY     → Firebase console → Project settings → Web API key
 * 3. FIREBASE_SERVICE_ACCOUNT → absolute path to the service-account JSON you
 *    downloaded from: Firebase console → Project settings → Service accounts
 *    → Generate new private key  (save it as firebase-service-account.json)
 */

define('FIREBASE_PROJECT_ID',      'YOUR_FIREBASE_PROJECT_ID');
define('FIREBASE_API_KEY',         'YOUR_FIREBASE_API_KEY');
define('FIREBASE_SERVICE_ACCOUNT', __DIR__ . '/../firebase-service-account.json');
