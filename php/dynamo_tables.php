<?php

$result = $client->listTables();

// TableNames contains an array of table names
$has_regions = false;
$has_collectors = false;
$has_collector_data = false;
foreach ($result['TableNames'] as $table_name) {
    if ($table_name == "collector_regions") {$has_regions = true;}
    if ($table_name == "collectors") {$has_collectors = true;}
    if ($table_name == "collector_data") {$has_collector_data = true;}
    if ($debug) {echo "Found Table: " . $table_name . "<br>\n";}
}


// Create tables if non-existent
if (!$has_regions ) {
    // This can take a few mintes so increase timelimit
    set_time_limit(600);
    
    if ($debug) {echo "Attempting to Create Table: collector_regions<br>\n";}
    $client->createTable(array(
        'TableName' => 'collector_regions',
        'AttributeDefinitions' => array(
            array(
                'AttributeName' => 'region_name',
                'AttributeType' => 'S'
            )
        ),
        'KeySchema' => array(
            array(
                'AttributeName' => 'region_name',
                'KeyType'       => 'HASH'
            )
        ),
        'ProvisionedThroughput' => array(
            'ReadCapacityUnits'  => 1,
            'WriteCapacityUnits' => 1
        )
    ));
    if ($debug) {echo "Created Table: collector_regions<br>\n";}
    $client->waitUntilTableExists(array('TableName' => 'collector_regions'));
    if ($debug) {echo "Table Exists!<br>\n";}
}


// Create tables if non-existent
if (!$has_collectors ) {
    // This can take a few mintes so increase timelimit
    set_time_limit(600);
    
    if ($debug) {echo "Attempting to Create Table: collectors<br>\n";}
    $client->createTable(array(
        'TableName' => 'collectors',
        'AttributeDefinitions' => array(
            array(
                'AttributeName' => 'collector_id',
                'AttributeType' => 'S'
            ),
            array(
                'AttributeName' => 'collector_region_name',
                'AttributeType' => 'S'
            )
        ),
        'KeySchema' => array(
            array(
                'AttributeName' => 'collector_id',
                'KeyType'       => 'HASH'
            ),
            array(
                'AttributeName' => 'collector_region_name',
                'KeyType'       => 'RANGE'
            )

        ),
        'ProvisionedThroughput' => array(
            'ReadCapacityUnits'  => 1,
            'WriteCapacityUnits' => 1
        )
    ));
    if ($debug) {echo "Created Table: collectors<br>\n";}
    $client->waitUntilTableExists(array('TableName' => 'collectors'));
    if ($debug) {echo "Table Exists!<br>\n";}
}


// Create tables if non-existent
if (!$has_collector_data ) {
    // This can take a few mintes so increase timelimit
    set_time_limit(600);
    
    if ($debug) {echo "Attempting to Create Table: collector_data<br>\n";}
    $client->createTable(array(
        'TableName' => 'collector_data',
        'AttributeDefinitions' => array(
            array(
                'AttributeName' => 'mac_id',
                'AttributeType' => 'S'
            ),
            array(
                'AttributeName' => 'collector_id',
                'AttributeType' => 'S'
            )
        ),
        'KeySchema' => array(
            array(
                'AttributeName' => 'mac_id',
                'KeyType'       => 'HASH'
            ),
            array(
                'AttributeName' => 'collector_id',
                'KeyType'       => 'RANGE'
            )

        ),
        'ProvisionedThroughput' => array(
            'ReadCapacityUnits'  => 1,
            'WriteCapacityUnits' => 1
        )
    ));
    if ($debug) {echo "Created Table: collector_data<br>\n";}
    $client->waitUntilTableExists(array('TableName' => 'collector_data'));
    if ($debug) {echo "Table Exists!<br>\n";}
}


?>
