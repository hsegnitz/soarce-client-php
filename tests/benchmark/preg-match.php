<?php

/**
 * Machine:  i9 9900K, 64GB RAM, Xubuntu 18.04, PHP 7.2
 * Sample trace file: 5MB, 71810 lines
 *
 * Runs in: 6.427 seconds
 */

class TraceParser1
{
    /** @var array[] temporary stack - will hold metadata of "open" functions */
    private $parseStack    = array();

    /** @var array[] end result that will be delivered to the service - one entry per function, listing type, number of calls and sum of walltime */
    private $parsedData    = array();

    /** @var int[]   internal function number index that assigns an ID for every function */
    private $functionIndex = array();

    /** @var int[][] functionId based map of what function calls which function how often: [caller][callee] => calls */
    private $functionMap   = array();

    /**
     * @param  resource $fp
     */
    public function analyze($fp)
    {
        while (false !== ($line = fgets($fp))) {
            $out = array();
            if (preg_match('/^[\d]+\t(?P<functionNumber>[\d]+)\t0\t(?P<start>[\d.]+)\t[\d]+\t(?P<functionName>[^\t]+)\t(?P<type>[01])\t[^\t]*\t(?P<file>[^\t]+)\t.*/', $line, $out)) {
                if ('' === $out['functionName']) {
                    continue;
                }

                if (!isset($this->functionIndex[$out['functionName']])) {
                    $this->functionIndex[$out['functionName']] = count($this->functionIndex);
                }

                $this->parseStack[$out['functionNumber']] = array(
                    'start'        => $out['start'],
                    'functionName' => $out['functionName'],
                    'number'       => $this->functionIndex[$out['functionName']],
                    'type'         => $out['type'],
                    'file'         => $out['file'],
                );

                if (count($this->parseStack) >= 2) {
                    $slice = array_slice($this->parseStack, count($this->parseStack)-2, 2);
                    $calleeLine = array_pop($slice);
                    $calleeId = $calleeLine['number'];

                    $callerLine = array_pop($slice);
                    $callerId = $callerLine['number'];

                    if (!isset($this->functionMap[$callerId])) {
                        $this->functionMap[$callerId] = array();
                    }
                    if (!isset($this->functionMap[$callerId][$calleeId])) {
                        $this->functionMap[$callerId][$calleeId] = 0;
                    }
                    $this->functionMap[$callerId][$calleeId]++;
                }

                continue;
            }

            if (preg_match('/^[\d]+\t(?P<functionNumber>[\d]+)\t1\t(?P<end>[\d.]+)\t[\d]+.*/', $line, $out)) {
                if (! isset($this->parseStack[$out['functionNumber']])) {
                    continue;
                }

                $info = $this->parseStack[$out['functionNumber']];
                unset($this->parseStack[$out['functionNumber']]);

                if (!isset($this->parsedData[$info['file']])) {
                    $this->parsedData[$info['file']] = array();
                }

                if (!isset($this->parsedData[$info['file']][$info['functionName']])) {
                    $this->parsedData[$info['file']][$info['functionName']] = array(
                        'type'     => $info['type'],
                        'count'    => 1,
                        'walltime' => (float)$out['end'] - (float)$info['start'],
                        'number'   => $info['number'],
                    );
                } else {
                    $this->parsedData[$info['file']][$info['functionName']]['count']++;
                    $this->parsedData[$info['file']][$info['functionName']]['walltime'] += ((float)$out['end'] - (float)$info['start']);
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getParsedData()
    {
        return $this->parsedData;
    }

    /**
     * @return array
     */
    public function getFunctionMap()
    {
        return $this->functionMap;
    }
}


$start = microtime(true);

for ($i = 0; $i < 100; $i++) {
    $fp = fopen('../PhpUnit_UnitTests/Fixtures/long-trace.xt', 'rb');
    $traceParser = new TraceParser1();
    $traceParser->analyze($fp);
}

echo microtime(true) - $start;
echo "\n";
