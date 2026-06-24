<?php
##########################################################################################
#                                     PDO overrides                                     #
##########################################################################################
uopz_set_return(
    'PDO',
    'query',
    function ($query, ...$args) {
        try{
            $result = $this->query($query, ...$args);
            $the_exception = null;
        }catch(Throwable $e) {
            $result = false;
            $the_exception = $e;
        }
        if ($result === false) {
            if($the_exception) {
                $errno = -1;
                $errstr = $the_exception->getMessage();
            } else {
                $errno = $this->errorCode();
                $errstr = $this->errorInfo();
            }
            $json = json_encode(
                [
                    'function' => 'PDO::query',
                    'params' => [$query],
                    'errno' => $errno,
                    'errstr' => $errstr,
                ]
            );
            __fuzzer_file_put_contents(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", $json . "\n", FILE_APPEND);
            chmod(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", 0777);
            if($the_exception != null) {
                throw $the_exception;
            }
        }
        return $result;
    },
    true
);

uopz_set_return(
    'PDO',
    'exec',
    function ($statement) {
        try{
            $result = $this->exec($statement);
            $the_exception = null;
        }catch(Throwable $e) {
            $result = false;
            $the_exception = $e;
        }
        if ($result === false) {
            if($the_exception) {
                $errno = -1;
                $errstr = $the_exception->getMessage();
            } else {
                $errno = $this->errorCode();
                $errstr = $this->errorInfo();
            }
            $json = json_encode(
                [
                    'function' => 'PDO::exec',
                    'params' => [$statement],
                    'errno' => $errno,
                    'errstr' => $errstr,
                ]
            );
            __fuzzer_file_put_contents(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", $json . "\n", FILE_APPEND);
            chmod(__FUZZER__MYSQL_ERRORS_PATH . __FUZZER__COVID . ".json", 0777);
            if($the_exception != null) {
                throw $e;
            }
        }
        return $result;
    },
    true
);
?>