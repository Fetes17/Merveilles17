<?php

Merveilles17::init();
Merveilles17::copy();
Merveilles17::load();
// Merveilles17::lieux();


/*



$html = array();
$last = null;
$first = true;
foreach ($biblio as $key => $value) {
  $type = explode('_', $key)[1];
  if ($type != $last) {
    if ($first) $first = false;
    else $html[] = "</div>";
    $html[] = "<div>";
    $html[] = "<h2>";
    if (isset($doctype[$type])) $html[] = $doctype[$type];
    else $html[] = $type;
    $html[] = "</h2>";
    $last = $type;
  }
  $html[] = $value;
}
$html[] = "</div>";

file_put_contents($home."site/biblio.html", str_replace("%main%", implode("\n", $html), $template));
*/

class Merveilles17
{
  /** SQLite link */
  static public $pdo;
  /** Home directory of project, absolute */
  static $home;
  /** Database absolute path */
  static private $sqlfile;
  /** HTML template */
  static private $template;
  /** SQL to create database */
  static private $create = "
PRAGMA encoding = 'UTF-8';
PRAGMA page_size = 8192;

CREATE TABLE doc (
  -- répertoire des documents
  id             INTEGER,               -- ! rowid auto
  code           TEXT UNIQUE NOT NULL,  -- ! code unique
  type           TEXT,                  -- ! type de document
  bibl           TEXT NOT NULL,         -- ! référence bibliographique (html)
  length         INTEGER,               -- ! taille en caractères
  personne_count INTEGER,               -- ! nombre de personnes citées
  lieu_count     INTEGER,               -- ! nombre de lieux
  tech_count     INTEGER,               -- ! nombre de techniques
  PRIMARY KEY(id ASC)
);
CREATE INDEX doc_type ON doc(type, code);

CREATE TABLE lieu (
  -- répertoire des lieux
  id             INTEGER,               -- ! rowid auto
  code           TEXT UNIQUE NOT NULL,  -- ! code unique
  term           TEXT NOT NULL,         -- ! forme de référence
  coord          TEXT,                  -- ? coordonnées carto
  locality       TEXT,                  -- ? commune, pour recherche
  alt            TEXT,                  -- ? forme alternative, pour recherche
  docs           INTEGER,               -- ! nombre de documents,  calculé, pour tri
  occs           INTEGER,               -- ! nombre d’occurrences, calculé, pour tri
  PRIMARY KEY(id ASC)
);
CREATE INDEX lieu_occs ON lieu(occs, code);
CREATE INDEX lieu_docs ON lieu(docs, code);

CREATE TABLE lieu_doc (
  -- Occurences d’un lieu dans un document
  id             INTEGER,               -- ! rowid auto
  lieu           INTEGER,               -- ! lieu.id obtenu avec par lieu.code
  lieu_code      TEXT NOT NULL,         -- ! lieu.code
  doc            INTEGER,               -- ! doc.id obtenu avec par doc.code
  doc_code       TEXT NOT NULL,         -- ! sera obtenu avec par doc.code
  anchor         TEXT NOT NULL,         -- ! ancre dans le fichier source
  occurrence     TEXT NOT NULL,         -- ! forme dans le texte
  desc           TEXT,                  -- ? description, à tirer du contexte
  PRIMARY KEY(id ASC)
);
CREATE INDEX lieu_doc_doc ON lieu_doc(doc);
CREATE INDEX lieu_doc_lieu ON lieu_doc(lieu);


CREATE TABLE technique (
  -- répertoire des techniques
  id             INTEGER,               -- ! rowid auto
  code           TEXT UNIQUE NOT NULL,  -- ! code unique
  term           TEXT NOT NULL,         -- ! forme d’autorité
  docs           INTEGER,               -- ! nombre de documents,  calculé, pour tri
  occs           INTEGER,               -- ! nombre d’occurrences, calculé, pour tri
  PRIMARY KEY(id ASC)
);
CREATE INDEX technique_occs ON technique(occs, code);
CREATE INDEX technique_docs ON technique(docs, code);

CREATE TABLE technique_doc (
  -- Occurences d’un technique dans un document
  id             INTEGER,               -- ! rowid auto
  technique      INTEGER,               -- ! technique.id obtenu avec par technique.code
  technique_code TEXT NOT NULL,         -- ! technique.code
  doc            INTEGER,               -- ! doc.id obtenu avec par doc.code
  doc_code       TEXT NOT NULL,         -- ! sera obtenu avec par doc.code
  anchor         TEXT NOT NULL,         -- ! ancre dans le fichier source
  occurrence     TEXT NOT NULL,         -- ! forme dans le texte
  PRIMARY KEY(id ASC)
);
CREATE INDEX technique_doc_doc ON technique_doc(doc);
CREATE INDEX technique_doc_technique ON technique_doc(technique);


CREATE TABLE personne (
  -- répertoire des personnes
  id             INTEGER,               -- ! rowid auto
  code           TEXT UNIQUE NOT NULL,  -- ! code unique
  term           TEXT NOT NULL,         -- ! forme dans le texte
  docs           INTEGER,               -- ! nombre de documents,  calculé, pour tri
  occs           INTEGER,               -- ! nombre d’occurrences, calculé, pour tri
  PRIMARY KEY(id ASC)
);
CREATE INDEX personne_occs ON personne(occs, code);
CREATE INDEX personne_docs ON personne(docs, code);

CREATE TABLE personne_doc (
  -- Occurences d’un nom de personne dans un document
  id             INTEGER,               -- ! rowid auto
  personne       INTEGER,               -- ! personne.id obtenu avec par personne.code
  personne_code  TEXT NOT NULL,         -- ! personne.code
  doc            INTEGER,               -- ! doc.id obtenu avec par doc.code
  doc_code       TEXT NOT NULL,         -- ! sera obtenu avec par doc.code
  anchor         TEXT NOT NULL,         -- ! ancre dans le ficheir source
  occurrence     TEXT NOT NULL,         -- ! forme dans le texte
  role           TEXT,                  -- ? @role
  PRIMARY KEY(id ASC)
);
CREATE INDEX personne_doc_personne ON personne_doc(personne);
CREATE INDEX personne_doc_doc ON personne_doc(doc);


  ";
  static private $doctype = array(
      "arc" => "Archives",
      "gr" => "Gravures",
      "i" => "Imprimés",
      "image" => "Images",
      "ms" => "Manuscrits",
      "p" => "Périodiques",
    );

  
  public static function init()
  {
    self::$home = dirname(dirname(__FILE__)).'/';
    self::$sqlfile = self::$home."site/merveilles17.sqlite";
    // recreate sqlite base on each call
    self::$pdo = Build::sqlcreate(self::$sqlfile, self::$create);
    self::$template = file_get_contents(self::$home."build/template.html");
  }
  
  /**
   * Load dictionaries in database
   */
  public static function load()
  {
    self::tsv_insert("lieu", array("code", "term", "coord", "locality", "alt"), file_get_contents(self::$home."index/lieu.tsv"));
    
    // different generated files    
    $readme = "
# Merveilles de la Cour, les textes

[Documentation du schema](https://fetes17.github.io/merveilles17/merveilles17.html)

";
    $biblio = array();
    $lieu_doc =           "lieu_code\tdoc_code\tanchor\toccurrence\tdesc\n";
    $technique_doc = "technique_code\tdoc_code\tanchor\toccurrence\n";
    $personne_doc =   "personne_code\tdoc_code\tanchor\toccurrence\trole\n";
    // loop on all xml files, and do lots of work
    foreach (glob(self::$home."xml/*.xml") as $srcfile) {
      echo basename($srcfile),"\n";
      $dom = Build::dom($srcfile);
      
      $readme .= "* [".basename($srcfile)."](https://fetes17.github.io/merveilles17/xml/".basename($srcfile).")\n";

      $dstname = basename($srcfile, ".xml");
      $dstfile = self::$home."site/".$dstname.".html";
      
      $biblio[$dstname] = Build::transformDoc($dom, self::$home."build/xsl/doc.xsl", null, array('name' => $dstname));
      $lieu_doc .= Build::transformDoc($dom, self::$home."build/xsl/lieu_doc.xsl", null, array('filename' => $dstname));
      $personne_doc .= Build::transformDoc($dom, self::$home."build/xsl/personne_doc.xsl", null, array('filename' => $dstname));
      
      /*      
      $main = Build::transformDoc($dom, $theme."document.xsl", null, array('filename' => $dstname, 'locorum' => $indexes['locorum']));
      file_put_contents($dstfile, str_replace("%main%", $main, $template));
      // data
      fwrite($fwplace, $place);
      fwrite($fwpers, $pers);
      $tech = Build::transformDoc($dom, $theme."tech.xsl", null, array('filename' => $dstname));
      fwrite($fwtech, $tech);
      */
    }
    file_put_contents(self::$home."README.md", $readme);

    return;
    
    // fill biblio
    $sql = "INSERT INTO doc (code, type, bibl) VALUES (:code, :type, :bibl);";
        $stmt = self::$pdo->prepare($sql);
    $stmt->bindParam('code', $code);
    $stmt->bindParam('type', $type);
    $stmt->bindParam('bibl', $type);
    self::$pdo->beginTransaction();
    foreach ($biblio as $code => $bibl) {
      $type = explode('_', $code)[1];
      $stmt->execute();
    }
    self::$pdo->commit();

    
    
    file_put_contents($home."index/lieu_doc.tsv", $lieu_doc);
    self::tsv_insert("lieu_doc", array("lieu_code", "doc_code", "anchor", "occurrence", "desc"), $lieu_doc);
    self::$pdo->exec("
      UPDATE lieu_doc SET
        lieu=(SELECT id FROM lieu WHERE code=lieu_doc.lieu_code),
        doc=(SELECT id FROM doc WHERE code=lieu_doc.doc_code)
      ;
    ");
    /*
    self::$pdo->exec("
      UPDATE lieu SET
        occs=(SELECT id FROM doc WHERE code=lieu_doc.doc_code),
        docs=(SELECT count(*) FROM lieu_doc WHERE person=person.id AND writes = 1)
      ;
    ");
    */
    file_put_contents($home."index/technique_doc.tsv", $technique_doc);
    self::tsv_insert("technique_doc", array("technique_code", "doc_code", "anchor", "occurrence"), $technique_doc);
    self::$pdo->exec("
      UPDATE technique_doc SET
        technique=(SELECT id FROM technique WHERE code=technique_doc.technique_code),
        doc=(SELECT id FROM doc WHERE code=technique_doc.doc_code)
      ;
    ");

    file_put_contents($home."index/personne_doc.tsv", $personne_doc);
    self::tsv_insert("personne_doc", array("personne_code", "doc_code", "anchor", "occurrence", "role"), $personne_doc);
    self::$pdo->exec("
      UPDATE personne_doc SET
        personne=(SELECT id FROM personne WHERE code=personne_doc.personne_code),
        doc=(SELECT id FROM doc WHERE code=personne_doc.doc_code)
      ;
    ");
    
  }
  
  /**
   * Générer les pages lieux
   */
  public static function lieux()
  {
    // boucler sur tous les lieux
    $sth = $dbh->prepare("SELECT * FROM lieu ORDER BY code");
    $sth->execute();
    
    
    file_put_contents($home."site/biblio.html", str_replace("%main%", implode("\n", $html), $template));
    $html =
'
<div class="row align-items-start">
  <div class="col-9">
  </div>
  <div class="col-3">
  </div>
</div>
';
  }

  /**
   * Charger une table avec des lignes tsv
   */  
  private static function tsv_insert($table, $cols, $lines)
  {
    $count = count($cols);
    $sql = "INSERT INTO ".$table." (".implode(", ", $cols).") VALUES (?".str_repeat (', ?', $count - 1).");";
    
    
    $stmt = self::$pdo->prepare($sql);
    $first = true;
    self::$pdo->beginTransaction();
    foreach (explode("\n", $lines) as $l){
      if (!$l) continue;
      if ($first) { // skip first line
        $first = false;
        continue;
      }
      $values = array_slice(explode("\t", $l), 0, $count);
      $stmt->execute($values);
    }
    self::$pdo->commit();
  }
  
  /**
   * Copy ressources to site
   */
  public static function copy()
  {
    $dstdir = self::$home."site/images"; // prudence
    Build::rmdir($dstdir);
    Build::rcopy(self::$home."build/images", $dstdir);
    $dstdir = self::$home."site/theme"; // prudence
    Build::rmdir($dstdir);
    Build::rcopy(self::$home."build/theme", $dstdir);
  }

}

/**
 * Different tools to build html sites
 */
class Build
{
  /** XSLTProcessors */
  private static $transcache = array();
  /** get a temp dir */
  private static $tmpdir;

  
  /**
   * get a pdo link to an sqlite database with good options
   */
  static function pdo($file, $sql)
  {
    $dsn = "sqlite:".$file;
    // if not exists, create
    if (!file_exists($file)) return self::sqlcreate($file, $sql);
    else return self::sqlopen($file, $sql);
  }
    
  /**
   * Open a pdo link
   */
  static private function sqlopen($file)
  {
    $dsn = "sqlite:".$file;
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("PRAGMA temp_store = 2;");
    return $pdo;
  }
  
  /**
   * Renew a database with an SQL script to create tables
   */
  static function sqlcreate($file, $sql)
  {
    if (file_exists($file)) unlink($file);
    self::mkdir(dirname($file));
    $pdo = self::sqlopen($file);
    @chmod($sqlite, 0775);
    $pdo->exec($sql);
    return $pdo;
  }

  /**
   * Get a DOM document with best options
   */
  static function dom($xmlfile) {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput=true;
    $dom->substituteEntities=true;
    $dom->load($xmlfile, LIBXML_NOENT | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOCDATA | LIBXML_NOWARNING);
    return $dom;
  }
  /**
   * Xsl transform from xml file
   */
  static function transform($xmlfile, $xslfile, $dst=null, $pars=null)
  {
    return self::transformDoc(self::dom($xmlfile), $xslfile, $dst, $pars);
  }

  static public function transformXml($xml, $xslfile, $dst=null, $pars=null)
  {
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput=true;
    $dom->substituteEntities=true;
    $dom->loadXml($xml, LIBXML_NOENT | LIBXML_NONET | LIBXML_NSCLEAN | LIBXML_NOCDATA | LIBXML_NOWARNING);
    return self::transformDoc($dom, $xslfile, $dst, $pars);
  }

  /**
   * An xslt transformer with cache
   * TOTHINK : deal with errors
   */
  static public function transformDoc($dom, $xslfile, $dst=null, $pars=null)
  {
    if (!is_a($dom, 'DOMDocument')) {
      throw new Exception('Source is not a DOM document, use transform() for a file, or transformXml() for an xml as a string.');
    }
    $key = realpath($xslfile);
    // cache compiled xsl
    if (!isset(self::$transcache[$key])) {
      $trans = new XSLTProcessor();
      $trans->registerPHPFunctions();
      // allow generation of <xsl:document>
      if (defined('XSL_SECPREFS_NONE')) $prefs = XSL_SECPREFS_NONE;
      else if (defined('XSL_SECPREF_NONE')) $prefs = XSL_SECPREF_NONE;
      else $prefs = 0;
      if(method_exists($trans, 'setSecurityPreferences')) $oldval = $trans->setSecurityPreferences($prefs);
      else if(method_exists($trans, 'setSecurityPrefs')) $oldval = $trans->setSecurityPrefs($prefs);
      else ini_set("xsl.security_prefs",  $prefs);
      $xsldom = new DOMDocument();
      $xsldom->load($xslfile);
      $trans->importStyleSheet($xsldom);
      self::$transcache[$key] = $trans;
    }
    $trans = self::$transcache[$key];
    // add params
    if(isset($pars) && count($pars)) {
      foreach ($pars as $key => $value) {
        $trans->setParameter(null, $key, $value);
      }
    }
    // return a DOM document for efficient piping
    if (is_a($dst, 'DOMDocument')) {
      $ret = $trans->transformToDoc($dom);
    }
    else if ($dst != '') {
      self::mkdir(dirname($dst));
      $trans->transformToURI($dom, $dst);
      $ret = $dst;
    }
    // no dst file, return String
    else {
      $ret =$trans->transformToXML($dom);
    }
    // reset parameters ! or they will kept on next transform if transformer is reused
    if(isset($pars) && count($pars)) {
      foreach ($pars as $key => $value) $trans->removeParameter(null, $key);
    }
    return $ret;
  }
  
  /**
   * A safe mkdir dealing with rights
   */
  static function mkdir($dir)
  {
    if (is_dir($dir)) return false;
    if (!mkdir($dir, 0775, true)) throw new Exception("Directory not created: ".$dir);
    @chmod(dirname($dst), 0775);  // let @, if www-data is not owner but allowed to write
  } 

  /**
   * Recursive deletion of a directory
   */
  static function rmdir($dir) {
    $dir = rtrim($dir, "/\\").DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) return false; // maybe deleted
    if(!($handle = opendir($dir))) throw new Exception("Read impossible ".$file);
    while(false !== ($filename = readdir($handle))) {
      if ($filename == "." || $filename == "..") continue;
      $file = $dir.$filename;
      if (is_link($file)) throw new Exception("Delete a link? ".$file);
      else if (is_dir($file)) self::rmdir($file);
      else unlink($file);
     }
    closedir($handle);
    rmdir($dir);
  }
  
  
  /**
   * Recursive copy of folder
   */
  static function rcopy($srcdir, $dstdir) {
    $srcdir = rtrim($srcdir, "/\\").DIRECTORY_SEPARATOR;
    $dstdir = rtrim($dstdir, "/\\").DIRECTORY_SEPARATOR;
    self::mkdir($dstdir);
    $dir = opendir($srcdir);
    while(false !== ($filename = readdir($dir))) {
      if ($filename[0] == '.') continue;
      $srcfile = $srcdir.$filename;
      if (is_dir($srcfile)) self::rcopy($srcfile, $dstdir.$filename);
      else copy($srcfile, $dstdir.$filename);
    }
    closedir($dir);
  }

}


 ?>
