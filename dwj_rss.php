<?php
require_once('/var/www/html/wordpress/wp-config.php');

$pdo = new PDO( 'mysql:dbname=' . DB_NAME . ';host=' . DB_HOST . ';charset=utf8', DB_USER, DB_PASSWORD );
if( $pdo != null ){
  $pdo->query( 'SET NAMES utf8' );
}
date_default_timezone_set( 'Asia/Tokyo' );


function insert( $content, $title, $datetime, $category ){
  global $pdo;
  $id = insert_post( $content, $title, $datetime );
  $term_taxonomy_id = insert_category( $category );

  $sql = "insert into wp_term_relationships(object_id, term_taxonomy_id) values(:id, :term_taxonomy_id)";
  $stmt = $pdo->prepare( $sql );
  $stmt->bindParam( ':id', $id );
  $stmt->bindParam( ':term_taxonomy_id', $term_taxonomy_id );
  $r = $stmt->execute();

  return $id;
}


function insert_post( $content, $title, $datetime ){
  global $pdo;
  $id = 0;

  if( !$datetime ){
    $datetime = localtime();
  }

  $sql1 = "insert into wp_posts(post_content, post_title, post_date, post_status, post_type) values(:post_content, :post_title, :post_date, 'publish', 'post')";
  $stmt1 = $pdo->prepare( $sql1 );
  $stmt1->bindParam( ':post_content', $content );
  $stmt1->bindParam( ':post_title', $title );
  $stmt1->bindParam( ':post_date', $datetime );
  $r1 = $stmt1->execute();

  $sql1a = "select last_insert_id() as id from wp_posts";
  $stmt1a = $pdo->query( $sql1a );
  if( $row = $stmt1a->fetch( PDO::FETCH_ASSOC ) ){
    $id = $row["id"];
  }

  return $id;
}


function insert_category( $category ){
  global $pdo;
  $term_taxonomy_id = 0;

  $term_id = 0;
  $sql0 = "select term_id from wp_terms where name = '" . $category . "'";
  $stmt0 = $pdo->query( $sql0 );
  if( $row0 = $stmt0->fetch( PDO::FETCH_ASSOC ) ){
    $term_id = $row0["term_id"];
  }

  if( $term_id ){
    //. カテゴリは存在済み => 記事数をインクリメント
    $sql1 = "update wp_term_taxonomy set count = count + 1 where term_id = :term_id";
    $stmt1 = $pdo->prepare( $sql1 );
    $stmt1->bindParam( ':term_id', $term_id );
    $r1 = $stmt1->execute();

    $sql1a = "select term_taxonomy_id from wp_term_taxonomy where term_id = " . $term_id;
    $stmt1a = $pdo->query( $sql1a );
    if( $row = $stmt1a->fetch( PDO::FETCH_ASSOC ) ){
      $term_taxonomy_id = $row["term_taxonomy_id"];
    }
  }else{
    //. カテゴリは存在していない => 作成
    $sql1 = "insert into wp_terms(name, slug) values(:name, :slug)";
    $stmt1 = $pdo->prepare( $sql1 );
    $stmt1->bindParam( ':name', $category );
    $stmt1->bindParam( ':slug', urlencode($category) );
    $r1 = $stmt1->execute();

    $sql1a = "select last_insert_id() as term_id from wp_terms";
    $stmt1a = $pdo->query( $sql1a );
    if( $row = $stmt1a -> fetch( PDO::FETCH_ASSOC ) ){
      $term_id = $row["term_id"];
    }

    $sql1b = "insert into wp_term_taxonomy(term_id, taxonomy, count) values(:term_id, 'category', 1 )";
    $stmt1b = $pdo->prepare( $sql1b );
    $stmt1b->bindParam( ':term_id', $term_id );
    $r1b = $stmt1b->execute();

    $sql1c = "select last_insert_id() as term_taxonomy_id from wp_term_taxonomy";
    $stmt1c = $pdo->query( $sql1c );
    if( $row = $stmt1c->fetch( PDO::FETCH_ASSOC ) ){
      $term_taxonomy_id = $row["term_taxonomy_id"];
    }
  }

  return $term_taxonomy_id;
}


//. https://www.ibm.com/developerworks/jp/feeds/
$topics = [
  "java" => "Java",
  "linux" => "Linux",
  "mobile" => "Mobile",
  "opensource" => "Open Source",
  "webservices" => "SOA and Web Services",
  "web" => "Web",
  "xml" => "XML",
  "cloud" => "Cloud"
];

//. https://www.ibm.com/developerworks/jp/views/*****/rss/libraryview.jsp
foreach( $topics as $key => $value ){
  $url = 'https://www.ibm.com/developerworks/jp/views/' . $key . '/rss/libraryview.jsp';
  $xml = file_get_contents( $url );
  $rss = new SimpleXMLElement( $xml );
  $items = $rss->channel[0]->item;

  for( $i = 0; $i < count($items); $i ++ ){
    $item = $items[$i];
    $title = $item->title[0];
    $desc = $item->description[0];
    $pubDate = $item->pubDate[0];

    //$local_dt = date( "Y-m-d H:i:s", strtotime( $pubDate ) );

    echo "$key [$i] : $title ($pubDate)\n";
    $post_id = insert( $desc, $title, $pubDate, $value );
    echo " -> $post_id\n";
  }
}

?>
