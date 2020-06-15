<?php
// Email notifications to be enable and triggered based on the MLR expiry date
// - 3 months prior expiry
// - 2 months prior expiry
// - 1 month prior expiry
// - Weekly from 1 month
// - And daily during last week
//for project gulf only

require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// SMTP configuration.
$mail = new PHPMailer();
$mail->isSMTP();
// $mail->SMTPDebug = 3;
$mail->Host = 'ndhsmtp.amer.pfizer.com';
//$mail->SMTPAuth = TRUE;
//$mail->Username = 'hany@orangestudio.com'; //paste one generated by Mailtrap
//$mail->Password = 'wcdwxnfswopizire'; //paste one generated by Mailtrap
$mail->SMTPAutoTLS = false;
$mail->Port = 25;

// Mail Headers.
$mail->setFrom('support.digicamp@pfizer.com', 'DigiCamp Support');
// $mail->addReplyTo('hany@orangestudio.com', 'Hany');
$mail->isHTML(TRUE);
$sent_messages_count = 0;

// Specify project ID = Gulf.
$project_id = 49;
$base_url = "http://digicamp.pfizer.com/issues.json?limit=100&project_id=" . $project_id;
$username = "admin";
$password = "Pfizer@123";
$periods = [90, 60, 30, 21, 14, 7, 6, 5, 4, 3, 2, 1];

try {
  $headers = [
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
  ];
  $httpClient = new Client();
  $response = $httpClient->request('GET', $base_url, [
    'headers' => $headers,
    'auth' => [$username, $password],
  ]);
  $data = $response->getBody()->getContents();
  $data = json_decode($data, TRUE);

  // Handling data offset.
  $loops = ceil($data['total_count'] / 100);

  for ($i = 0; $i < $loops; $i++) {
    $offset = $i * 100;
    $url = $base_url . "&offset=" . $offset;
    $response = $httpClient->request('GET', $url, [
      'headers' => $headers,
      'auth' => [$username, $password],
    ]);
    $data = $response->getBody()->getContents();
    $data = json_decode($data, TRUE);

    foreach ($data['issues'] as $issue) {
      $send = FALSE;
      foreach ($issue['custom_fields'] as $field) {

        if ($field["name"] == "Content Owner Name") {
          $name = $field["value"];
        }
        elseif ($field["name"] == "Content Owner Email") {
          $to = $field["value"];
        }
        elseif ($field["name"] == "Content is removed") {
          $removed = $field["value"];
        }
        elseif ($field["name"] == "MLR Expiry Date") {
          $expiry_date = $field["value"];
          $today = new DateTime("today");
          $expiry_date2 = new DateTime($expiry_date);
          $interval = $expiry_date2->diff($today);
          $diff_days = $interval->days;

          if ($expiry_date2 > $today && in_array($diff_days, $periods)) {
            $send = TRUE;
          }
          else {
            break;
          }
        }
      }

      if (isset($name, $to, $expiry_date) && $send && !$removed) {
        // Send email if all data is set.
        sendMail($name, $to, $diff_days, $issue["id"], $issue["subject"]);
        $send = FALSE;
      }

    }
  }

  echo $sent_messages_count . ' message(s) have been sent.';
  exit(0);
} catch (GuzzleException $e) {
  return $e->getMessage();
}

/*
 * Helper function to send email.
 */
function sendMail($name, $to, $expiry_date, $issue_id, $issue_subject) {
  $mail = $GLOBALS["mail"];
  $mail->addAddress($to, $name);
  $mail->Subject = $issue_subject . ' - MLR expiry notice';
  $mailContent = "
        Dear " . $name . ",<br>Your <a href='https://digicamp.pfizer.com/issues/" . $issue_id . "'>content</a> is expiring in " . $expiry_date . " day(s), if you do not renew or update material, you are giving consent for DCE to remove the content.
        <br><br>
        Best Regards,
        <br>
        DigiCamp Team";
  $mail->Body = $mailContent;
  if ($mail->send()) {
    $GLOBALS["sent_messages_count"]++;
  }
  else {
    echo 'Message could not be sent.';
    echo 'Mailer Error: ' . $mail->ErrorInfo;
    exit(1);
  }
}