<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TeaSeat</title>
</head>
<body>
<p> Hello World!</p>
<?php 

$connection=pg_connect("host = localhost port = 5432 dbname=teaseat user=postgres password =4626");
var_dump($connection);


if ($connection) {
  echo"соединение установлено";
}


$query = "select * from nameproduct";
$result = pg_query($connection, $query);
if (!$result) {
  echo "Произошла ошибка.\n";
  exit;
}
while ($row = pg_fetch_row($result)) {
  echo "<br />\n";
  echo "айди: $row[0] что-то там ещё: $row[1], и ещё : $row[1] ";
  
}
?>
</body>
</html>
