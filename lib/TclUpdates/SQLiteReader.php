<?php

namespace TclUpdates;

class SQLiteReader
{
    const OTA_ONLY = 0;
    const FULL_ONLY = 1;
    const BOTH =2;
    private $dbFile;
    private $pdo;

    public function __construct()
    {
        $this->dbFile = __DIR__ . '/../../otadb.db3';
        $this->pdo = new \PDO('sqlite:' . $this->dbFile);
        if ($this->pdo === false) {
            return false;
        }
        $this->pdo->exec('PRAGMA foreign_keys=on;');
    }

    public function getAllRefs()
    {
        $sql = 'SELECT DISTINCT curef FROM updates ORDER BY curef;';
        $sqlresult = $this->pdo->query($sql);
        $result = array();
        foreach ($sqlresult as $row) {
            $result[] = $row[0];
        }
        return $result;
    }

    public function getAllKnownRefs()
    {
        $sql = 'SELECT DISTINCT curef FROM devices ORDER BY curef;';
        $sqlresult = $this->pdo->query($sql);
        $result = array();
        foreach ($sqlresult as $row) {
            $result[] = $row[0];
        }
        return $result;
    }

    public function getUnknownRefs()
    {
        $knownPrds = $this->getAllKnownRefs();
        $allPrds   = $this->getAllRefs();
        $unknownPrds = array_diff($allPrds, $knownPrds);
        return $unknownPrds;
    }

    public function getAllVariants()
    {
        $sql = 'SELECT f.name, m.name, d.curef, d.name FROM families f LEFT JOIN models m ON f.familyId=m.familyId LEFT JOIN devices d ON m.modelId=d.modelId;';
        $sqlresult = $this->pdo->query($sql);
        $result = array();
        foreach ($sqlresult as $row) {
            $family = $row[0];
            $model  = $row[1];
            $curef = $row[2];
            $variant = $row[3];
            if (!isset($result[$family])) {
                $result[$family] = array();
            }
            if (!isset($result[$family][$model])) {
                $result[$family][$model] = array();
            }
            $result[$family][$model][$curef] = $variant;
        }
        return $result;
    }

    public function getAllVariantsFlat()
    {
        $sql = 'SELECT f.name AS family, m.name AS model, d.curef, d.name AS variant FROM families f LEFT JOIN models m ON f.familyId=m.familyId LEFT JOIN devices d ON m.modelId=d.modelId;';
        $sqlresult = $this->pdo->query($sql);
        $result = array();
        foreach ($sqlresult->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[$row['curef']] = $row['family'] . ' ' . $row['model'];
            if (strlen($row['variant'])>0) {
                $result[$row['curef']] .= ' (' . $row['variant'] . ')';
            }
        }
        return $result;
    }

    public function getAllVariantsByRef()
    {
        $sql = 'SELECT f.name AS family, m.name AS model, d.curef, d.name AS variant FROM families f LEFT JOIN models m ON f.familyId=m.familyId LEFT JOIN devices d ON m.modelId=d.modelId;';
        $sqlresult = $this->pdo->query($sql);
        $result = array();
        foreach ($sqlresult->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $result[$row['curef']] = array(
                'family' => $row['family'],
                'model' => $row['model'],
                'variant' => $row['variant'],
            );
        }
        return $result;
    }

    public function getAllUpdates($curef, $which = self::BOTH)
    {
        $sql = 'SELECT * FROM updates u LEFT JOIN files f ON u.file_sha1=f.sha1 WHERE curef=?';
        if ($which == self::OTA_ONLY) {
            $sql .= ' AND fv IS NOT null';
        } elseif ($which == self::FULL_ONLY) {
            $sql .= ' AND fv IS null';
        }
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute(array($curef));
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }

    public function getAllUpdatesForFile($sha1)
    {
        $sql = 'SELECT * FROM updates u WHERE u.file_sha1=? ORDER BY pubDate ASC';
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute(array($sha1));
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }

    public function getAllFiles($which = self::BOTH)
    {
        $sql = 'SELECT * FROM files f';
        if ($which == self::OTA_ONLY) {
            $sql .= ' WHERE fv IS NOT null';
        } elseif ($which == self::FULL_ONLY) {
            $sql .= ' WHERE fv IS null';
        }
        $sql .= ' ORDER BY published_first DESC';
        $stmt = $this->pdo->query($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $result;
    }

    public function getLatestUpdate($curef, $which = self::BOTH)
    {
        $sql = 'SELECT * FROM updates u LEFT JOIN files f ON u.file_sha1=f.sha1 WHERE curef=?';
        if ($which == self::OTA_ONLY) {
            $sql .= ' AND fv IS NOT null';
        } elseif ($which == self::FULL_ONLY) {
            $sql .= ' AND fv IS null';
        }
        $sql .= ' ORDER BY tv DESC, fv DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute(array($curef));
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (count($result) == 1) {
            $result = reset($result);
        }
        return $result;
    }

    public function getAllVersionsForRef($curef = null, $which = self::BOTH)
    {
        $sql = 'SELECT fv, tv FROM updates u LEFT JOIN files f ON u.file_sha1=f.sha1';
        $where_arr = array();
        $params_arr = array();
        if (!is_null($curef)) {
            $where_arr[] = 'curef=?';
            $params_arr[] = $curef;
        }
        if ($which == self::OTA_ONLY) {
            $where_arr[] = 'fv IS NOT null';
        } elseif ($which == self::FULL_ONLY) {
            $where_arr[] = 'fv IS null';
        }
        if (count($where_arr) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where_arr);
        }
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute($params_arr);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $version = array();
        foreach ($result as $row) {
            if (!is_null($row['fv']) && $which == self::BOTH) {
                $version[] = $row['fv'];
            }
            $version[] = $row['tv'];
        }
        $version = array_unique($version);
        sort($version);
        return $version;
    }

    public function getAllVersionsForModel($model)
    {
        $sql = 'SELECT fv, tv FROM models m LEFT JOIN devices d ON m.modelId=d.modelId LEFT JOIN updates u ON d.curef=u.curef LEFT JOIN files f ON u.file_sha1=f.sha1 WHERE m.name=?';
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute(array($model));
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $version = array();
        foreach ($result as $row) {
            if (!is_null($row['fv'])) {
                $version[] = $row['fv'];
            }
            if (!is_null($row['tv'])) {
                $version[] = $row['tv'];
            }
        }
        $version = array_unique($version);
        sort($version);
        return $version;
    }

    public function getAllRefsForFile($sha1)
    {
        $sql = 'SELECT curef FROM updates u WHERE u.file_sha1=?';
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute(array($sha1));
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $refs = array();
        foreach ($result as $row) {
            $refs[] = $row['curef'];
        }
        return $refs;
    }
}
