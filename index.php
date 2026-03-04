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
                print $iface_title[$if];
            }
            else
            {
                print $if;
            }
            print "</a>";
            print "<ul class=\"page\">\n";
            foreach ($page_list as $pg)
            {
                $page_class = ($iface == $if && $page == $pg) ? 'page active' : 'page';
                print "<li class=\"$page_class\"><a href=\"$script?if=$if$p&amp;page=$pg\">".$page_title[$pg]."</a></li>\n";
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

    function kbytes_to_mb_string($kb)
    {
        $bytes = $kb * 1024;
        $megabytes = $bytes / (1000 * 1000);

        return number_format($megabytes, 2, '.', ',')." MB";
    }

    function kbytes_to_gb_string($kb)
    {
        $bytes = $kb * 1024;
        $gigabytes = $bytes / (1000 * 1000 * 1000);

        return number_format($gigabytes, 2, '.', '')." GB";
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
        print "<th class=\"label\" style=\"width:120px;\" rowspan=\"2\">&nbsp;</th>";
        print "<th class=\"label\" colspan=\"2\">".T('In')."</th>";
        print "<th class=\"label\" colspan=\"2\">".T('Out')."</th>";
        print "<th class=\"label\" colspan=\"2\">".T('Total')."</th>";
        print "</tr>\n";
        print "<tr>";
        print "<th class=\"label\">MB</th>";
        print "<th class=\"label gb_col\">GB</th>";
        print "<th class=\"label\">MB</th>";
        print "<th class=\"label gb_col\">GB</th>";
        print "<th class=\"label\">MB</th>";
        print "<th class=\"label gb_col\">GB</th>";
        print "</tr>\n";

        for ($i=0; $i<count($tab); $i++)
        {
            if ($tab[$i]['act'] == 1)
            {
                $t = $tab[$i]['label'];
                $rx_mb = kbytes_to_mb_string($tab[$i]['rx']);
                $rx_gb = kbytes_to_gb_string($tab[$i]['rx']);
                $tx_mb = kbytes_to_mb_string($tab[$i]['tx']);
                $tx_gb = kbytes_to_gb_string($tab[$i]['tx']);
                $total_mb = kbytes_to_mb_string($tab[$i]['rx']+$tab[$i]['tx']);
                $total_gb = kbytes_to_gb_string($tab[$i]['rx']+$tab[$i]['tx']);
                $id = ($i & 1) ? 'odd' : 'even';
                print "<tr>";
                print "<td class=\"label_$id\">$t</td>";
                print "<td class=\"numeric_$id\">$rx_mb</td>";
                print "<td class=\"numeric_$id gb_col\">$rx_gb</td>";
                print "<td class=\"numeric_$id\">$tx_mb</td>";
                print "<td class=\"numeric_$id gb_col\">$tx_gb</td>";
                print "<td class=\"numeric_$id\">$total_mb</td>";
                print "<td class=\"numeric_$id gb_col\">$total_gb</td>";
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
    if ($page != 's')
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
  setInterval(updatePanel, 500);
})();
</script>

</body></html>
