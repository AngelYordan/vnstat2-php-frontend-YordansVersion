<?php
    //
    // vnStat PHP frontend (c)2006-2010 Bjorge Dijkstra (bjd@jooz.net)
    //
    // This program is free software; you can redistribute it and/or modify
    // it under the terms of the GNU General Public License as published by
    // the Free Software Foundation; either version 2 of the License, or
    // (at your option) any later version.
    //
    // This program is distributed in the hope that it will be useful,
    // but WITHOUT ANY WARRANTY; without even the implied warranty of
    // MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    // GNU General Public License for more details.
    //
    // You should have received a copy of the GNU General Public License
    // along with this program; if not, write to the Free Software
    // Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
    //
    //
    // see file COPYING or at http://www.gnu.org/licenses/gpl.html
    // for more information.
    //

    //
    // Valid values for other parameters you can pass to the script.
    // Input parameters will always be limited to one of the values listed here.
    // If a parameter is not provided or invalid it will revert to the default,
    // the first parameter in the list.
    //
    if (isset($_SERVER['PHP_SELF']))
    {
        $script = $_SERVER['PHP_SELF'];
    }
    elseif (isset($_SERVER['SCRIPT_NAME']))
    {
        $script = $_SERVER['SCRIPT_NAME'];
    }
    else
    {
        die('can\'t determine script name!');
    }

    $page_list  = array('s','h','d','m','q');

    $graph_list = array('large','small','none');

    $page_title['s'] = T('summary');
    $page_title['h'] = T('hours');
    $page_title['d'] = T('days');
    $page_title['m'] = T('months');
    $page_title['q'] = 'Consultas';


    //
    // functions
    //
    function strftime_to_icu_pattern($format)
    {
        $map = array(
            '%a' => 'EEE',
            '%b' => 'MMM',
            '%B' => 'MMMM',
            '%d' => 'dd',
            '%H' => 'HH',
            '%k' => 'H',
            '%l' => 'h',
            '%m' => 'MM',
            '%M' => 'mm',
            '%p' => 'a',
            '%Y' => 'yyyy',
            '%%' => '%',
        );

        $pattern = '';
        $literal = '';
        $length = strlen($format);

        for ($i = 0; $i < $length; $i++) {
            $char = $format[$i];

            if ($char === '%' && $i + 1 < $length) {
                $token = '%' . $format[$i + 1];

                if (isset($map[$token])) {
                    if ($literal !== '') {
                        $pattern .= "'" . str_replace("'", "''", $literal) . "'";
                        $literal = '';
                    }

                    $pattern .= $map[$token];
                    $i++;
                    continue;
                }
            }

            $literal .= $char;
        }

        if ($literal !== '') {
            $pattern .= "'" . str_replace("'", "''", $literal) . "'";
        }

        return $pattern;
    }


    function strftime_compat($format, $timestamp)
    {
        global $locale;

        if (class_exists('IntlDateFormatter')) {
            $intl_locale = preg_replace('/\..*$/', '', (string)$locale);
            if ($intl_locale === null || $intl_locale === '') {
                $intl_locale = 'en_US';
            }

            $pattern = strftime_to_icu_pattern($format);
            $formatter = new IntlDateFormatter(
                $intl_locale,
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                date_default_timezone_get(),
                IntlDateFormatter::GREGORIAN,
                $pattern
            );

            if ($formatter !== false) {
                $formatted = $formatter->format($timestamp);
                if ($formatted !== false) {
                    return $formatted;
                }
            }
        }

        $php_format = strtr($format, array(
            '%a' => 'D',
            '%b' => 'M',
            '%B' => 'F',
            '%d' => 'd',
            '%H' => 'H',
            '%k' => 'G',
            '%l' => 'g',
            '%m' => 'm',
            '%M' => 'i',
            '%p' => 'A',
            '%Y' => 'Y',
            '%%' => '%',
        ));

        return date($php_format, $timestamp);
    }


    function validate_input()
    {
        global $page,  $page_list;
        global $iface, $iface_list;
        global $graph, $graph_list;
        global $colorscheme, $style;
        //
        // get interface data
        //
        $page = isset($_GET['page']) ? $_GET['page'] : '';
        $iface = isset($_GET['if']) ? $_GET['if'] : '';
        $graph = isset($_GET['graph']) ? $_GET['graph'] : '';
        $style = isset($_GET['style']) ? $_GET['style'] : '';

        if (!in_array($page, $page_list))
        {
            $page = $page_list[0];
        }

        if (!in_array($iface, $iface_list))
        {
            $iface = $iface_list[0];
        }

        if (!in_array($graph, $graph_list))
        {
            $graph = $graph_list[0];
        }

        $tp = "./themes/$style";
        if (!is_dir($tp) || !file_exists("$tp/theme.php") || !preg_match('/^[a-z0-9-_]+$/i', $style))
        {
            $style = DEFAULT_COLORSCHEME;
        }
    }


    function get_vnstat_data($use_label=true)
    {
        global $iface, $vnstat_bin, $data_dir;
        global $hour,$day,$day_all,$month,$top,$summary;

        $vnstat_data = array();
        if (!isset($vnstat_bin) || $vnstat_bin == '')
        {
            if (file_exists("$data_dir/vnstat_dump_$iface"))
            {
                $file_data = file_get_contents("$data_dir/vnstat_dump_$iface");
                $vnstat_data = json_decode($file_data, TRUE);
            }
        }
        else
        {
            // FIXME: use mode and limit parameter to reduce data that needs to be parsed
            $fd = popen("$vnstat_bin --json -i $iface", "r");
            if (is_resource($fd))
            {
                $buffer = '';
                while (!feof($fd)) {
                    $buffer .= fgets($fd);
                }
                pclose($fd);
                $vnstat_data = json_decode($buffer, TRUE);
            }
        }

        $day = array();
        $hour = array();
        $month = array();
        $day_all = array();
        $top = array();

        if (!isset($vnstat_data) || !isset($vnstat_data['vnstatversion'])) {
            return;
        }

        $iface_data = $vnstat_data['interfaces'][0];
        $traffic_data = $iface_data['traffic'];
        // data are grouped for hour, day, month, ... and a data entry looks like this:
        // [0] => Array
        //   (
        //     [id] => 48032
        //     [date] => Array
        //       (
        //         [year] => 2020
        //         [month] => 8
        //         [day] => 23
        //       )
        //     [time] => Array
        //       (
        //         [hour] => 16
        //         [minute] => 0
        //       )
        //     [rx] => 2538730
        //     [tx] => 2175640
        //   )

        // per-day data
        // FIXME: instead of using array_reverse, sorting by date/time keys would be more reliable
        $day_data = array_reverse($traffic_data['day']);
        for($i = 0; $i < count($day_data); $i++) {
            $d = $day_data[$i];
            $ts = mktime(0, 0, 0, $d['date']['month'], $d['date']['day'], $d['date']['year']);

            $day_all[$i]['time'] = $ts;
            $day_all[$i]['rx'] = $d['rx'] / 1024;
            $day_all[$i]['tx'] = $d['tx'] / 1024;
            $day_all[$i]['act'] = 1;

            if($use_label) {
                $day_all[$i]['label'] = strftime_compat(T('datefmt_days'), $ts);
                $day_all[$i]['img_label'] = strftime_compat(T('datefmt_days_img'), $ts);
            }

            if ($i < 30) {
                $day[$i] = $day_all[$i];
            }
        }

        // per-month data
        $month_data = array_reverse($traffic_data['month']);
        for($i = 0; $i < min(12, count($month_data)); $i++) {
            $d = $month_data[$i];
            $ts = mktime(0, 0, 0, $d['date']['month']+1, 0, $d['date']['year']);

            $month[$i]['time'] = $ts;
            $month[$i]['rx'] = $d['rx'] / 1024;
            $month[$i]['tx'] = $d['tx'] / 1024;
            $month[$i]['act'] = 1;

            if($use_label) {
                $month[$i]['label'] = strftime_compat(T('datefmt_months'), $ts);
                $month[$i]['img_label'] = strftime_compat(T('datefmt_months_img'), $ts);
            }
        }

        // per-hour data
        $hour_data = array_reverse($traffic_data['hour']);
        for($i = 0; $i < min(24, count($hour_data)); $i++) {
            $d = $hour_data[$i];
            $ts = mktime($d['time']['hour'], $d['time']['minute'], 0, $d['date']['month'], $d['date']['day'], $d['date']['year']);

            $hour[$i]['time'] = $ts;
            $hour[$i]['rx'] = $d['rx'] / 1024;
            $hour[$i]['tx'] = $d['tx'] / 1024;
            $hour[$i]['act'] = 1;

            if($use_label) {
                $hour[$i]['label'] = strftime_compat(T('datefmt_hours'), $ts);
                $hour[$i]['img_label'] = strftime_compat(T('datefmt_hours_img'), $ts);
            }
        }

        // top10 days data
        $top10_data = $traffic_data['top'];
        for($i = 0; $i < min(10, count($top10_data)); $i++) {
            $d = $top10_data[$i];
            $ts = mktime(0, 0, 0, $d['date']['month'], $d['date']['day'], $d['date']['year']);

            $top[$i]['time'] = $ts;
            $top[$i]['rx'] = $d['rx'] / 1024;
            $top[$i]['tx'] = $d['tx'] / 1024;
            $top[$i]['act'] = 1;

            if($use_label) {
                $top[$i]['label'] = strftime_compat(T('datefmt_top'), $ts);
                $top[$i]['img_label'] = '';
            }
        }

        // summary data from old dumpdb command
        // all time total received/transmitted MB
        $summary['totalrx'] = $traffic_data['total']['rx'] / 1024 / 1024;
        $summary['totaltx'] = $traffic_data['total']['tx'] / 1024 / 1024;
        // FIXME: used to be "total rx kB counter" from dumpdb, no idea how to get those
        $summary['totalrxk'] = 0;
        $summary['totaltxk'] = 0;
        $summary['interface'] = $iface_data['name'];
    }
?>
