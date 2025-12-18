<?php

use Infobip\Configuration;
use Infobip\Api\SmsApi;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsTextualMessage;
use Infobip\Model\SmsAdvancedTextualRequest;

require__DIR__ . "/vendor/autoload.php";

$phone = $_POST["number"];
$code = rand(100000, 999999);

$_POST["provider"] === "infobip";

$base_url = "https://38qq91.api.infobip.com";
$api_key = "b98b7e6f1c64a648cfc7fc617f6afc9e-2ff681db-6441-4158-b6af-4574d16fa02f";

$configuration = new Configuration(host: $base_url, apiKey: $api_key);

$api = new SmsApi(configuration: $configuration);

$destination = new SmsDestination(to: $phone);

$message = new SmsTextualMessage(
    destinations: [$destination],
    text: "Your verification code is: $code",
    from: "COACH"
);

$request = new SmsAdvancedTextualRequest(messages: [$message]);

$response = $api->sendSmsMessage ($request);

echo "Message sent";