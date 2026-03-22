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
    require 'config.php';
    require 'localize.php';
    require 'vnstat.php';

    validate_input();

    require "./themes/$style/theme.php";

    function write_side_bar()
    {
        global $iface, $page, $graph, $script, $style;
        global $iface_list, $iface_title;
        global $page_list, $page_title;

        $p = "&amp;graph=$graph&amp;style=$style";

        print "<ul class=\"iface\">\n";
        foreach ($iface_list as $if)
        {
            if ($iface == $if) {
                print "<li class=\"iface active\">";
            } else {
            print "<li class=\"iface\">";
            }
            print "<a href=\"$script?if=$if$p\">";
            if (isset($iface_title[$if]))
            {
                print ucfirst($iface_title[$if]);
            }
            else
            {
                print ucfirst($if);
            }
            print "</a>";
            print "<ul class=\"page\">\n";
            foreach ($page_list as $pg)
            {
                $page_class = ($iface == $if && $page == $pg) ? 'page active' : 'page';
                print "<li class=\"$page_class\"><a href=\"$script?if=$if$p&amp;page=$pg\">".ucfirst($page_title[$pg])."</a></li>\n";
            }
            print "</ul></li>\n";
        }
        print "</ul>\n";
    }


    function kbytes_to_string($kb)
    {

        global $byte_notation;

        $bytes = $kb * 1024;

        $binary_units = array(
            'TiB' => 1024*1024*1024*1024,
            'GiB' => 1024*1024*1024,
            'MiB' => 1024*1024,
            'KiB' => 1024,
        );

        $decimal_units = array(
            'TB' => 1000*1000*1000*1000,
            'GB' => 1000*1000*1000,
            'MB' => 1000*1000,
            'KB' => 1000,
        );

        if (isset($byte_notation) && is_string($byte_notation)) {
            $preferred_unit = strtoupper(trim($byte_notation));

            if (isset($decimal_units[$preferred_unit])) {
                return sprintf("%0.2f %s", ($bytes/$decimal_units[$preferred_unit]), $preferred_unit);
            }

            $binary_aliases = array(
                'TIB' => 'TiB',
                'GIB' => 'GiB',
                'MIB' => 'MiB',
                'KIB' => 'KiB',
            );

            if (isset($binary_aliases[$preferred_unit])) {
                $preferred_unit = $binary_aliases[$preferred_unit];
            }

            if (isset($binary_units[$preferred_unit])) {
                return sprintf("%0.2f %s", ($bytes/$binary_units[$preferred_unit]), $preferred_unit);
            }
        }

        foreach ($binary_units as $unit => $divisor)
        {
            if ($bytes >= $divisor) {
                return sprintf("%0.2f %s", ($bytes/$divisor), $unit);
            }
        }

        return sprintf("%0.2f KiB", ($bytes/1024));
    }

    function kbytes_to_dynamic_string($kb)
    {
        $bytes = $kb * 1024;
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        $unit_index = 0;
        $value = $bytes;

        while ($value >= 1000 && $unit_index < count($units) - 1)
        {
            $value = $value / 1000;
            $unit_index++;
        }

        return number_format($value, 2, '.', ',')." ".$units[$unit_index];
    }

    function write_summary()
    {
        global $summary,$top,$day,$hour,$month;

        $trx = $summary['totalrx']*1024+$summary['totalrxk'];
        $ttx = $summary['totaltx']*1024+$summary['totaltxk'];

        //
        // build array for write_data_table
        //

        $sum = array();

        if (count($day) > 0 && count($hour) > 0 && count($month) > 0) {
            $sum[0]['act'] = 1;
            $sum[0]['label'] = T('This hour');
            $sum[0]['rx'] = $hour[0]['rx'];
            $sum[0]['tx'] = $hour[0]['tx'];

            $sum[1]['act'] = 1;
            $sum[1]['label'] = T('This day');
            $sum[1]['rx'] = $day[0]['rx'];
            $sum[1]['tx'] = $day[0]['tx'];

            $sum[2]['act'] = 1;
            $sum[2]['label'] = T('This month');
            $sum[2]['rx'] = $month[0]['rx'];
            $sum[2]['tx'] = $month[0]['tx'];

            $sum[3]['act'] = 1;
            $sum[3]['label'] = T('All time');
            $sum[3]['rx'] = $trx;
            $sum[3]['tx'] = $ttx;
        }

        write_data_table(T('Summary'), $sum);
        print "<br/>\n";
        write_data_table(T('Top 10 days'), $top);
    }


    function write_data_table($caption, $tab)
    {
        print "<table width=\"100%\" cellspacing=\"0\">\n";
        print "<caption>$caption</caption>\n";
        print "<tr>";
        print "<th class=\"label\" style=\"width:120px;\">&nbsp;</th>";
        print "<th class=\"label\">".T('In')."</th>";
        print "<th class=\"label\">".T('Out')."</th>";
        print "<th class=\"label\">".T('Total')."</th>";
        print "</tr>\n";

        for ($i=0; $i<count($tab); $i++)
        {
            if ($tab[$i]['act'] == 1)
            {
                $t = $tab[$i]['label'];
                $rx_value = kbytes_to_dynamic_string($tab[$i]['rx']);
                $tx_value = kbytes_to_dynamic_string($tab[$i]['tx']);
                $total_value = kbytes_to_dynamic_string($tab[$i]['rx']+$tab[$i]['tx']);
                $id = ($i & 1) ? 'odd' : 'even';
                print "<tr>";
                print "<td class=\"label_$id\">$t</td>";
                print "<td class=\"numeric_$id\">$rx_value</td>";
                print "<td class=\"numeric_$id\">$tx_value</td>";
                print "<td class=\"numeric_$id\">$total_value</td>";
                print "</tr>\n";
             }
        }
        print "</table>\n";
    }

    function parse_iso_date($date_value)
    {
        if (!is_string($date_value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_value)) {
            return false;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $date_value);
        if ($dt === false || $dt->format('Y-m-d') !== $date_value) {
            return false;
        }

        return mktime(0, 0, 0, (int)$dt->format('m'), (int)$dt->format('d'), (int)$dt->format('Y'));
    }

    function filter_days_between($days, $from_ts, $to_ts)
    {
        $filtered = array();

        for ($i=0; $i<count($days); $i++)
        {
            if (!isset($days[$i]['time']) || $days[$i]['time'] < $from_ts || $days[$i]['time'] > $to_ts) {
                continue;
            }
            $filtered[] = $days[$i];
        }

        return $filtered;
    }

    function get_query_group_label($query_group)
    {
        if ($query_group == 'y') {
            return 'Agrupar por Año';
        }
        if ($query_group == 'm') {
            return 'Agrupar por Mes';
        }
        if ($query_group == 'h') {
            return 'Agrupar por Hora';
        }

        return 'Agrupar por Día';
    }

    function build_query_rows($query_group, $day_all, $hour)
    {
        $grouped = array();
        $format = '%d %b %Y';
        $source = $day_all;

        if ($query_group == 'h') {
            $source = $hour;
            $format = '%d %b %Y %H:00';
        } else if ($query_group == 'm') {
            $format = '%b %Y';
        } else if ($query_group == 'y') {
            $format = '%Y';
        }

        for ($i = 0; $i < count($source); $i++)
        {
            if (!isset($source[$i]['time'])) {
                continue;
            }

            $ts = $source[$i]['time'];
            if ($query_group == 'm') {
                $bucket = mktime(0, 0, 0, (int)date('n', $ts), 1, (int)date('Y', $ts));
            } else if ($query_group == 'y') {
                $bucket = mktime(0, 0, 0, 1, 1, (int)date('Y', $ts));
            } else if ($query_group == 'h') {
                $bucket = mktime((int)date('G', $ts), 0, 0, (int)date('n', $ts), (int)date('j', $ts), (int)date('Y', $ts));
            } else {
                $bucket = mktime(0, 0, 0, (int)date('n', $ts), (int)date('j', $ts), (int)date('Y', $ts));
            }

            if (!isset($grouped[$bucket])) {
                $grouped[$bucket] = array(
                    'time' => $bucket,
                    'rx' => 0,
                    'tx' => 0,
                    'act' => 1,
                );
            }

            $grouped[$bucket]['rx'] += $source[$i]['rx'];
            $grouped[$bucket]['tx'] += $source[$i]['tx'];
        }

        krsort($grouped);
        $rows = array_values($grouped);
        for ($i = 0; $i < count($rows); $i++)
        {
            $rows[$i]['label'] = strftime_compat($format, $rows[$i]['time']);
        }

        return $rows;
    }

    function write_query_results($rows, $query_page, $query_group, $query_from_date, $query_to_date)
    {
        global $iface, $style, $script;

        $per_page = 20;
        $total_rows = count($rows);
        $total_pages = max(1, (int)ceil($total_rows / $per_page));
        $query_page = max(1, min($query_page, $total_pages));
        $start = ($query_page - 1) * $per_page;
        $visible_rows = array_slice($rows, $start, $per_page);
        $end = $start + count($visible_rows);

        $base_params = "if=".rawurlencode($iface)."&page=q&graph=none&style=".rawurlencode($style)
            ."&q_group=".rawurlencode($query_group)
            ."&q_from_date=".rawurlencode($query_from_date)
            ."&q_to_date=".rawurlencode($query_to_date);

        print "<div class=\"query-layout\">\n";
        print "<div class=\"query-controls\">\n";
        print "<form id=\"query-form\" method=\"get\" action=\"$script\">\n";
        print "<input type=\"hidden\" name=\"if\" value=\"".htmlspecialchars($iface, ENT_QUOTES, 'UTF-8')."\"/>\n";
        print "<input type=\"hidden\" name=\"page\" value=\"q\"/>\n";
        print "<input type=\"hidden\" name=\"graph\" value=\"none\"/>\n";
        print "<input type=\"hidden\" name=\"style\" value=\"".htmlspecialchars($style, ENT_QUOTES, 'UTF-8')."\"/>\n";
        print "<div class=\"date-field\"><label for=\"q_from_date\">From Date</label><input id=\"q_from_date\" name=\"q_from_date\" type=\"date\" value=\"".htmlspecialchars($query_from_date, ENT_QUOTES, 'UTF-8')."\"/></div>\n";
        print "<div class=\"date-field\"><label for=\"q_to_date\">To Date</label><input id=\"q_to_date\" name=\"q_to_date\" type=\"date\" value=\"".htmlspecialchars($query_to_date, ENT_QUOTES, 'UTF-8')."\"/></div>\n";
        print "<div class=\"date-field\"><label for=\"q_group\">Agrupar por</label><select id=\"q_group\" name=\"q_group\">";
        print "<option value=\"y\"".($query_group == 'y' ? ' selected="selected"' : '').">Años</option>";
        print "<option value=\"m\"".($query_group == 'm' ? ' selected="selected"' : '').">Meses</option>";
        print "<option value=\"d\"".($query_group == 'd' ? ' selected="selected"' : '').">Días</option>";
        print "<option value=\"h\"".($query_group == 'h' ? ' selected="selected"' : '').">Horas</option>";
        print "</select></div>\n";
        print "<button type=\"submit\">Go</button>\n";
        print "</form>\n";
        print "<div class=\"query-meta\">Search found $total_rows results.</div>\n";
        print "<a class=\"query-export\" href=\"$script?$base_params&amp;export=1\">Export Results</a>\n";
        print "</div>\n";

        print "<div class=\"query-results\">\n";
        print "<table width=\"100%\" cellspacing=\"0\">\n";
        print "<caption>".get_query_group_label($query_group)."</caption>\n";
        print "<tr><th class=\"label\">Date</th><th class=\"label\">Download</th><th class=\"label\">Upload</th><th class=\"label\">Combined</th></tr>\n";

        if (count($visible_rows) == 0) {
            print "<tr><td class=\"label_even\" colspan=\"4\">No hay datos para mostrar.</td></tr>\n";
        } else {
            for ($i = 0; $i < count($visible_rows); $i++)
            {
                $id = ($i & 1) ? 'odd' : 'even';
                $rx = kbytes_to_dynamic_string($visible_rows[$i]['rx']);
                $tx = kbytes_to_dynamic_string($visible_rows[$i]['tx']);
                $total = kbytes_to_dynamic_string($visible_rows[$i]['rx'] + $visible_rows[$i]['tx']);
                print "<tr>";
                print "<td class=\"label_$id\">".$visible_rows[$i]['label']."</td>";
                print "<td class=\"numeric_$id\">$rx</td>";
                print "<td class=\"numeric_$id\">$tx</td>";
                print "<td class=\"numeric_$id\">$total</td>";
                print "</tr>\n";
            }
        }
        print "</table>\n";

        print "<div class=\"query-pagination\">";
        print "<span>Displaying ".($total_rows > 0 ? $start + 1 : 0)." to $end of $total_rows items</span>";
        if ($query_page > 1) {
            print "<a href=\"$script?$base_params&amp;q_page=".($query_page - 1)."\">&laquo; Anterior</a>";
        }
        if ($query_page < $total_pages) {
            print "<a href=\"$script?$base_params&amp;q_page=".($query_page + 1)."\">Siguiente &raquo;</a>";
        }
        print "</div>\n";
        print "</div>\n";
        print "</div>\n";
    }

    get_vnstat_data();

    $from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
    $to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
    $custom_day_range = array();
    $custom_day_error = '';

    if ($page == 'd' && ($from_date !== '' || $to_date !== ''))
    {
        $from_ts = parse_iso_date($from_date);
        $to_ts = parse_iso_date($to_date);

        if ($from_ts === false || $to_ts === false) {
            $custom_day_error = 'Selecciona ambas fechas en formato válido.';
        } elseif ($from_ts > $to_ts) {
            $custom_day_error = 'La fecha inicial no puede ser mayor que la final.';
        } else {
            $custom_day_range = filter_days_between($day_all, $from_ts, $to_ts);
        }
    }

    $query_from_date = isset($_GET['q_from_date']) ? $_GET['q_from_date'] : '';
    $query_to_date = isset($_GET['q_to_date']) ? $_GET['q_to_date'] : '';
    $query_group = isset($_GET['q_group']) ? $_GET['q_group'] : 'd';
    $query_page = isset($_GET['q_page']) ? (int)$_GET['q_page'] : 1;
    $query_rows = array();

    if (!in_array($query_group, array('y', 'm', 'd', 'h'))) {
        $query_group = 'd';
    }

    if ($page == 'q')
    {
        $source_days = $day_all;
        if ($query_group == 'h') {
            $source_days = $hour;
        }

        if ($query_from_date !== '' || $query_to_date !== '')
        {
            $query_from_ts = parse_iso_date($query_from_date);
            $query_to_ts = parse_iso_date($query_to_date);

            if ($query_from_ts !== false && $query_to_ts !== false && $query_from_ts <= $query_to_ts) {
                $source_days = filter_days_between($source_days, $query_from_ts, $query_to_ts + 86399);
            } else {
                $source_days = array();
            }
        }

        if ($query_group == 'h') {
            $query_rows = build_query_rows('h', array(), $source_days);
        } else {
            $query_rows = build_query_rows($query_group, $source_days, array());
        }

        if (isset($_GET['export']) && $_GET['export'] == '1')
        {
            header('Content-type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="consultas.csv"');
            print "Date,Download,Upload,Combined\n";
            for ($i = 0; $i < count($query_rows); $i++)
            {
                $rx = kbytes_to_dynamic_string($query_rows[$i]['rx']);
                $tx = kbytes_to_dynamic_string($query_rows[$i]['tx']);
                $total = kbytes_to_dynamic_string($query_rows[$i]['rx'] + $query_rows[$i]['tx']);
                print "\"".$query_rows[$i]['label']."\",\"$rx\",\"$tx\",\"$total\"\n";
            }
            exit;
        }
    }

    //
    // html start
    //
    header('Content-type: text/html; charset=utf-8');
    print '<?xml version="1.0"?>';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
  <title>vnStat - PHP frontend</title>
  <link rel="stylesheet" type="text/css" href="themes/<?php echo $style ?>/style.css"/>
</head>
<body>

<div id="wrap">
  <div id="sidebar"><?php write_side_bar(); ?></div>
   <div id="content">
    <div id="live-traffic-panel">
      <div class="title"><?php print T('Traffic data for')." ".(isset($iface_title[$iface]) ? $iface_title[$iface] : $iface); ?></div>
      <div id="live-rx" class="metric">In: --</div>
      <div id="live-tx" class="metric">Out: --</div>
      <div id="live-status">Actualizando cada 0.5s</div>
    </div>
    <div id="header"><?php print T('Traffic data for').(isset($iface_title[$iface]) ? $iface_title[$iface] : '')." ($iface)";?></div>
    <div id="main">
    <?php
    if ($page == 'd')
    {
        print "<form id=\"date-range-form\" method=\"get\" action=\"$script\">\n";
        print "<input type=\"hidden\" name=\"if\" value=\"".htmlspecialchars($iface, ENT_QUOTES, 'UTF-8')."\"/>\n";
        print "<input type=\"hidden\" name=\"page\" value=\"".htmlspecialchars($page, ENT_QUOTES, 'UTF-8')."\"/>\n";
        print "<input type=\"hidden\" name=\"graph\" value=\"".htmlspecialchars($graph, ENT_QUOTES, 'UTF-8')."\"/>\n";
        print "<input type=\"hidden\" name=\"style\" value=\"".htmlspecialchars($style, ENT_QUOTES, 'UTF-8')."\"/>\n";
        print "<div class=\"date-field\"><label for=\"from_date\">From Date</label><input id=\"from_date\" name=\"from_date\" type=\"date\" value=\"".htmlspecialchars($from_date, ENT_QUOTES, 'UTF-8')."\"/></div>\n";
        print "<div class=\"date-field\"><label for=\"to_date\">To Date</label><input id=\"to_date\" name=\"to_date\" type=\"date\" value=\"".htmlspecialchars($to_date, ENT_QUOTES, 'UTF-8')."\"/></div>\n";
        print "<button type=\"submit\">Buscar</button>\n";
        print "</form>\n";

        if ($custom_day_error !== '') {
            print "<div class=\"date-range-message error\">$custom_day_error</div>\n";
        } elseif (($from_date !== '' || $to_date !== '') && count($custom_day_range) === 0) {
            print "<div class=\"date-range-message\">No hay datos para el rango seleccionado.</div>\n";
        }
    }

    $graph_params = "if=$iface&amp;page=$page&amp;style=$style";
    if ($page == 'h' || $page == 'd' || $page == 'm')
        if ($graph_format == 'svg') {
	     print "<object type=\"image/svg+xml\" width=\"692\" height=\"297\" data=\"graph_svg.php?$graph_params\"></object>\n";
        } else {
	     print "<img src=\"graph.php?$graph_params\" alt=\"graph\"/>\n";
        }

    if ($page == 's')
    {
        write_summary();
    }
    else if ($page == 'h')
    {
        write_data_table(T('Last 24 hours'), $hour);
    }
    else if ($page == 'd')
    {
        if (count($custom_day_range) > 0) {
            write_data_table('Días en el rango seleccionado', $custom_day_range);
        }
        else if ($from_date === '' && $to_date === '') {
            write_data_table(T('Last 30 days'), $day);
        }
    }
    else if ($page == 'm')
    {
        write_data_table(T('Last 12 months'), $month);
    }
    else if ($page == 'q')
    {
        write_query_results($query_rows, $query_page, $query_group, $query_from_date, $query_to_date);
    }
    ?>
    </div>
    <div id="footer"><a href="http://www.sqweek.com/">vnStat PHP frontend</a> 2.0.0 - &copy;2006-2011 Bjorge Dijkstra (bjd _at_ jooz.net)</div>
  </div>
</div>

<script type="text/javascript">
(function () {
  var lastSample = null;

  function formatRate(bytesPerSecond) {
    var units = ['B/s', 'KB/s', 'MB/s', 'GB/s'];
    var value = bytesPerSecond;
    var index = 0;

    while (value >= 1000 && index < units.length - 1) {
      value = value / 1000;
      index += 1;
    }

    return value.toFixed(2) + ' ' + units[index];
  }

  function updatePanel() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'live.php?if=<?php print rawurlencode($iface); ?>&style=<?php print rawurlencode($style); ?>', true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) {
        return;
      }

      if (xhr.status !== 200) {
        document.getElementById('live-status').textContent = 'Error al obtener datos en tiempo real';
        return;
      }

      var data;
      try {
        data = JSON.parse(xhr.responseText);
      } catch (e) {
        document.getElementById('live-status').textContent = 'Error al procesar datos';
        return;
      }

      if (!lastSample) {
        lastSample = data;
        return;
      }

      var elapsed = (data.timestamp_ms - lastSample.timestamp_ms) / 1000;
      if (elapsed <= 0) {
        lastSample = data;
        return;
      }

      var rxRate = (data.rx_bytes - lastSample.rx_bytes) / elapsed;
      var txRate = (data.tx_bytes - lastSample.tx_bytes) / elapsed;

      if (rxRate < 0) { rxRate = 0; }
      if (txRate < 0) { txRate = 0; }

      document.getElementById('live-rx').textContent = 'In: ' + formatRate(rxRate);
      document.getElementById('live-tx').textContent = 'Out: ' + formatRate(txRate);
      document.getElementById('live-status').textContent = 'Actualizado: ' + (new Date()).toLocaleTimeString();

      lastSample = data;
    };

    xhr.send();
  }

  updatePanel();
  setInterval(updatePanel, 1200);
})();
</script>

</body></html>
