<?php

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\CustomFieldsValues\CheckboxCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\CheckboxCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\CheckboxCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Models\LeadModel;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\Dotenv\Dotenv;

include_once 'vendor/autoload.php';


if (!isset($_GET['email']) && !isset($_GET['name']) && !isset($_GET['price'])) {
    exit('INVALID REQUEST');
}


$email = urldecode($_GET['email']);
$name = urldecode($_GET['name']);
$price = $_GET['price'];
$is_interested = $_GET['is_interested'];

$dotenv = new Dotenv;
$dotenv->load('.env');

$apiClient = new AmoCRMApiClient(
    $_ENV['CLIENT_ID'], 
    $_ENV['CLIENT_SECRET'], 
    $_ENV['CLIENT_REDIRECT_URI']
    );

$apiClient->setAccountBaseDomain($_ENV['ACCOUNT_DOMAIN']);

$rawToken = json_decode(file_get_contents('token.json'), 1);
$token = new AccessToken($rawToken);

$apiClient->setAccessToken($token);

$contact = new ContactModel();
$phone = urldecode($_GET['phone']);
$contactCFV = new CustomFieldsValuesCollection();
if (isset($_GET['phone'])) {
    $contactCFV->add((new MultitextCustomFieldValuesModel())
        ->setFieldCode('PHONE')
        ->setValues((new MultitextCustomFieldValueCollection())
            ->add((new MultitextCustomFieldValueModel())
                ->setValue($phone)
            )
        )
    );
}
$contactCFV->add((new MultitextCustomFieldValuesModel())
    ->setFieldCode('EMAIL')
    ->setValues((new MultitextCustomFieldValueCollection())
        ->add((new MultitextCustomFieldValueModel())
            ->setValue(urldecode($email))
        )
    )
);
$contact
    ->setName($name)
    ->setCustomFieldsValues($contactCFV);
try {
    $contact = $apiClient->contacts()->addOne($contact);
} catch (AmoCRMApiException $e) {
    printError($e);
    die;
}

$lead = (new LeadModel)->setName("Новая сделка с {$name}")
    ->setPrice($price)
    ->setCustomFieldsValues((new CustomFieldsValuesCollection)
        ->add((new  CheckboxCustomFieldValuesModel)
            ->setFieldId($_ENV['IS_INTERESTED_ID'])
            ->setValues((new CheckboxCustomFieldValueCollection)
                ->add((new CheckboxCustomFieldValueModel)
                    ->setValue($is_interested)
                )
            )
        )
    );
try {
$lead = $apiClient->leads()->addOne($lead);
} catch (AmoCRMApiException $e) {
    printError($e);
    die;
}

$links = new LinksCollection();
$links->add($lead);
try {
    $apiClient->contacts()->link($contact, $links);
} catch (AmoCRMApiException $e) {
    printError($e);
    die;
}


echo "OK. LEAD_ID: {$lead->getId()}";