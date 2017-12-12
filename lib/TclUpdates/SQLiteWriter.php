<?php

namespace TclUpdates;

class SQLiteWriter
{
    private $dbFile;
    private $pdo;

    public function __construct()
    {
        $this->dbFile = 'otadb.db3';
        $this->pdo = new \PDO('sqlite:' . $this->dbFile, 0666, $sqlerror);
        if ($this->pdo === false) {
            return $sqlerror;
        }
    }

    private function insertArray($table, $data, $replace = false)
    {
        $placeholders = array_fill(0, count($data), '?');
        $sql = 'INSERT ';
        if ($replace) {
            $sql .= 'OR REPLACE ';
        }
        $sql .= 'INTO "' . $table . '" (' . implode(', ', array_keys($data)) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute(array_values($data));
        return $ok;
    }

    public function addFile($file_arr)
    {
        // Try fetch previous entry
        $sql = 'SELECT * FROM "files" WHERE "sha1"=?';
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute(array($file_arr['file_sha1']));
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $pubFirst = '2099-12-31';
        $pubLast  = '1970-01-01';
        $note = array('en' => null, 'ja' => null, 'zh' => null);
        if (count($result) > 0) {
            $pubFirst = $result[0]['published_first'];
            $pubLast  = $result[0]['published_last'];
            $note = json_decode($result[0]['note'], true);
            $hasChanged = false;
        } else {
            $hasChanged = true;
        }

        if (strtotime($file_arr['pubDate']) < strtotime($pubFirst)) {
            $pubFirst = $file_arr['pubDate'];
            $hasChanged = true;
        }

        if (strtotime($file_arr['pubDate']) > strtotime($pubLast)) {
            $pubLast = $file_arr['pubDate'];
            $hasChanged = true;
        }

        foreach ($file_arr['note'] as $lang => $desc) {
            // TODO: Maybe improve, i.e. compare for different contents
            if (strpos($desc, chr(0xc3)) !== false) {
                // fix double-UTF-8 encoding
                $desc = utf8_decode($desc);
            }
            if (mb_strlen($desc) > mb_strlen($note[$lang])) {
                $note[$lang] = $desc;
                $hasChanged = true;
            }
        }

        if ($hasChanged) {
            $this->insertArray('files', array(
                'sha1' => $file_arr['file_sha1'],
                'file_name' => $file_arr['file_name'],
                'file_size' => $file_arr['file_size'],
                'type' => $file_arr['type'],
                'note' => json_encode($note, JSON_UNESCAPED_UNICODE),
                'published_first' => $pubFirst,
                'published_last' => $pubLast,
            ), true);
        }
    }

    public function addGotu(GotuObject $g)
    {
        $this->addFile(array(
            'file_sha1' => $g->file_chksum,
            'file_name' => $g->filename,
            'file_size' => $g->file_size,
            'type' => $g->type,
            'note' => array(
                'en' => $g->description_en,
                'ja' => $g->description_ja,
                'zh' => $g->description_zh,
            ),
            'pubDate' => $g->time,
        ));
        $ok = $this->insertArray('updates', array(
            'tv' => $g->tv,
            'fv' => $g->fv,
            'svn' => $g->svn,
            'pubDate' => $g->time,
            'publisher' => $g->publisher,
            'fwId' => $g->fw_id,
            'file_id' => $g->file_id,
            'file_sha1' => $g->file_chksum,
        ));
        if ($ok) {
            $key = $this->pdo->lastInsertId();
            echo "Added entry " . $key . PHP_EOL;
        } else {
            echo "FAILED inserting." . PHP_EOL;
        }
    }
}