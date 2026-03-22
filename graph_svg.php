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

    function svg_create($width, $height)
    {
	header('Content-type: image/svg+xml');
	print "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\"?>\n";
	print "<svg width=\"$width\" height=\"$height\" version=\"1.2\" baseProfile=\"tiny\" xmlns=\"http://www.w3.org/2000/svg\">\n";
	print "<g style=\"shape-rendering: crispEdges\">\n";
    }

    function svg_end()
    {
	print "</g>\n";
	print "</svg>\n";
    }

    function svg_options($options)
    {
	foreach ($options as $key => $value) {
	    print "$key=\"$value\" ";
	}
    }

    function svg_group($options)
    {
	print "<g ";
	svg_options($options);
	print ">\n";
    }

    function svg_group_end()
    {
	print "</g>\n";
    }

    function svg_text($x, $y, $text, $options = array())
    {
	printf("<text x=\"%F\" y=\"%F\" ", $x, $y);
	svg_options($options);
	print ">$text</text>\n";
    }

    function svg_line($x1, $y1, $x2, $y2, $options = array())
    {
	printf("<line x1=\"%F\" y1=\"%F\" x2=\"%F\" y2=\"%F\" ", $x1, $y1, $x2, $y2);
	svg_options($options);
	print "/>\n";
    }

    function svg_rect($x, $y, $w, $h, $options = array())
    {
	printf("<rect x=\"%F\" y=\"%F\" width=\"%F\" height=\"%F\" ", $x, $y, $w, $h);
	svg_options($options);
	print "/>\n";
    }

    function svg_poly($points, $options = array())
    {
       print "<polygon points=\"";
       for ($p = 0; $p < count($points); $p += 2) {
	  printf("%F,%F ", $points[$p], $points[$p+1]);
       }
       svg_options($options);
       print "\"/>\n";
    }

    function svg_polyline($points, $options = array())
    {
       print "<polyline points=\"";
       for ($p = 0; $p < count($points); $p += 2) {
	  printf("%F,%F ", $points[$p], $points[$p+1]);
       }
       print "\" ";
       svg_options($options);
       print "/>\n";
    }

    function allocate_color($colors)
    {
	$col['rgb'] = sprintf("#%02X%02X%02X", $colors[0], $colors[1], $colors[2]);
	$col['opacity'] = sprintf("%F", (127 - $colors[3]) / 127);
	return $col;
    }

    function init_image()
    {
        global $xlm, $xrm, $ytm, $ybm, $iw, $ih,$graph, $cl, $iface, $colorscheme, $style;

        if ($graph == 'none')
            return;

        //
        // image object
        //
        $xlm = 72;
        $xrm = 24;
        $ytm = 42;
        $ybm = 68;
        if ($graph == 'small')
        {
            $iw = 300 + $xrm + $xlm;
            $ih = 140 + $ytm + $ybm;
        }
        else
        {
            $iw = 600 + $xrm + $xlm;
            $ih = 260 + $ytm + $ybm;
        }

	svg_create($iw, $ih);

        //
        // colors
        //
	$cs = $colorscheme;
	$cl['image_background'] = allocate_color($cs['image_background']);
	$cl['background'] = allocate_color($cs['graph_background']);
	$cl['background_2'] = allocate_color($cs['graph_background_2']);
        $cl['grid_stipple_1'] = allocate_color($cs['grid_stipple_1']);
        $cl['grid_stipple_2'] = allocate_color($cs['grid_stipple_2']);
        $cl['text'] = allocate_color($cs['text']);
        $cl['border'] = allocate_color($cs['border']);
	$cl['rx'] = array('rgb' => '#E53935', 'opacity' => '0.90');
	$cl['rx_border'] = array('rgb' => '#C62828', 'opacity' => '1.0');
	$cl['tx'] = array('rgb' => '#2E7D32', 'opacity' => '0.90');
	$cl['tx_border'] = array('rgb' => '#1B5E20', 'opacity' => '1.0');
	$cl['total'] = array('rgb' => '#1E88E5', 'opacity' => '0.90');
	$cl['total_border'] = array('rgb' => '#0D47A1', 'opacity' => '1.0');

	svg_rect(0, 0, $iw, $ih, array( 'stroke' => 'none', 'stroke-width' => 0, 'fill' => $cl['image_background']['rgb']) );
	svg_rect($xlm, $ytm, $iw-$xrm-$xlm, $ih-$ybm-$ytm, array( 'stroke' => 'none', 'stroke-width' => 0, 'fill' => $cl['background']['rgb']) );

	// draw title
	$text = T('Traffic data for')." $iface";
	svg_text($iw / 2, ($ytm / 2) + 2, $text, array( 'stroke' => 'none', 'fill' => $cl['text']['rgb'],'stroke-width' => 0, 'font-family' => SVG_FONT, 'font-weight' => 'bold', 'font-size' => '11pt', 'text-anchor' => 'middle' ));
    }

    function draw_border()
    {
        global $cl, $iw, $ih;
	svg_rect(1, 1, $iw-2, $ih-2, array( 'stroke' => $cl['border']['rgb'], 'stroke-opacity' => $cl['border']['opacity'], 'stroke-width' => 1, 'fill' => 'none') );
    }

    function draw_grid($x_ticks, $y_ticks)
    {
        global $cl, $iw, $ih, $xlm, $xrm, $ytm, $ybm;
        $x_step = ($iw - $xlm - $xrm) / ($x_ticks ?: 1);
        $y_step = ($ih - $ytm - $ybm) / $y_ticks;

	svg_group( array( 'stroke' => $cl['grid_stipple_1']['rgb'], 'stroke-opacity' => '0.20', 'stroke-width' => '1px', 'stroke-dasharray' => '1,4' ) );
        for ($i = $xlm; $i <= ($iw - $xrm); $i += $x_step)
        {
	    svg_line($i, $ytm, $i, $ih-$ybm);
        }
        for ($i = $ytm; $i <= ($ih - $ybm); $i += $y_step)
        {
            svg_line($xlm, $i, $iw - $xrm, $i);
        }
	svg_group_end();

	svg_group( array( 'stroke' => $cl['border']['rgb'], 'stroke-width' => '1px', 'stroke-opacity' => '0.45' ) );
        svg_line($xlm, $ytm, $xlm, $ih - $ybm);
        svg_line($xlm, $ih - $ybm, $iw - $xrm, $ih - $ybm);
	svg_group_end();
    }


    function draw_data($data)
    {
        global $cl,$iw,$ih,$xlm,$xrm,$ytm,$ybm;
        global $show_rx, $show_tx, $show_total;

        sort($data);

        $x_ticks = count($data);
        $y_ticks = 10;
        $y_scale = 1;
        $prescale = 1;
        $unit = 'K';
        $offset = 0;
        $gr_h = $ih - $ytm - $ybm;
        $x_step = ($iw - $xlm - $xrm) / ($x_ticks ?: 1);
        $y_step = ($ih - $ytm - $ybm) / $y_ticks;
        //
        // determine scale
        //
        $low = 99999999999;
        $high = 0;
        for ($i=0; $i<$x_ticks; $i++)
        {
            if ($show_rx === '1' && $data[$i]['rx'] > $high)
            $high = $data[$i]['rx'];
            if ($show_tx === '1' && $data[$i]['tx'] > $high)
            $high = $data[$i]['tx'];
            if ($show_total === '1' && ($data[$i]['rx'] + $data[$i]['tx']) > $high)
            $high = ($data[$i]['rx'] + $data[$i]['tx']);
        }

        while ($high > ($prescale * $y_scale * $y_ticks))
        {
            $y_scale = $y_scale * 2;
            if ($y_scale >= 1024)
            {
            $prescale = $prescale * 1024;
            $y_scale = $y_scale / 1024;
            if ($unit == 'K')
                $unit = 'M';
            else if ($unit == 'M')
                $unit = 'G';
            else if ($unit == 'G')
                $unit = 'T';
            }
        }

        draw_grid($x_ticks, $y_ticks);

        //
        // graph scale factor (per pixel)
        //
        $sf = ($prescale * $y_scale * $y_ticks) / $gr_h;

        if (count($data) == 0)
        {
            $text = 'no data available';
	    svg_text($iw/2, $ytm + 80, $text, array( 'stroke' => $cl['text']['rgb'], 'fill' => $cl['text']['rgb'], 'stroke-width' => 0, 'font-family' => SVG_FONT, 'font-size' => '16pt', 'text-anchor' => 'middle') );
        }
        else
        {
            $rx_points = array();
            $tx_points = array();
            $total_points = array();

            for ($i=0; $i<$x_ticks; $i++)
            {
        	$x = (int)($xlm + ($i * $x_step) + ($x_step / 2));
        	if ($show_rx === '1') {
		    $rx_y = (int)($ytm + ($ih - $ytm - $ybm) - (($data[$i]['rx'] - $offset) / $sf));
		    $rx_points[] = $x;
		    $rx_points[] = $rx_y;
		}
        	if ($show_tx === '1') {
		    $tx_y = (int)($ytm + ($ih - $ytm - $ybm) - (($data[$i]['tx'] - $offset) / $sf));
		    $tx_points[] = $x;
		    $tx_points[] = $tx_y;
		}
        	if ($show_total === '1') {
		    $total = $data[$i]['rx'] + $data[$i]['tx'];
		    $total_y = (int)($ytm + ($ih - $ytm - $ybm) - (($total - $offset) / $sf));
		    $total_points[] = $x;
		    $total_points[] = $total_y;
		}
            }

	    if (count($rx_points) > 1) {
	    svg_polyline($rx_points, array(
		'stroke' => $cl['rx']['rgb'], 'stroke-opacity' => '1.0', 'stroke-width' => '2',
		'fill' => 'none', 'stroke-linejoin' => 'round', 'stroke-linecap' => 'round'
	    ));
	    }
	    if (count($tx_points) > 1) {
	    svg_polyline($tx_points, array(
		'stroke' => $cl['tx']['rgb'], 'stroke-opacity' => '1.0', 'stroke-width' => '2',
		'fill' => 'none', 'stroke-linejoin' => 'round', 'stroke-linecap' => 'round'
	    ));
	    }
	    if (count($total_points) > 1) {
	    svg_polyline($total_points, array(
		'stroke' => $cl['total']['rgb'], 'stroke-opacity' => '1.0', 'stroke-width' => '2',
		'fill' => 'none', 'stroke-linejoin' => 'round', 'stroke-linecap' => 'round'
	    ));
	    }

	    for ($i = 0; $i < count($rx_points); $i += 2) {
		svg_rect($rx_points[$i] - 3, $rx_points[$i + 1] - 3, 6, 6, array(
		    'stroke' => $cl['rx_border']['rgb'], 'stroke-width' => 2, 'fill' => '#FFFFFF', 'rx' => '3', 'ry' => '3'
		));
	    }
	    for ($i = 0; $i < count($tx_points); $i += 2) {
		svg_rect($tx_points[$i] - 3, $tx_points[$i + 1] - 3, 6, 6, array(
		    'stroke' => $cl['tx_border']['rgb'], 'stroke-width' => 2, 'fill' => '#FFFFFF', 'rx' => '3', 'ry' => '3'
		));
	    }
	    for ($i = 0; $i < count($total_points); $i += 2) {
		svg_rect($total_points[$i] - 3, $total_points[$i + 1] - 3, 6, 6, array(
		    'stroke' => $cl['total_border']['rgb'], 'stroke-width' => 2, 'fill' => '#FFFFFF', 'rx' => '3', 'ry' => '3'
		));
	    }

            //
            // axis labels
            //
	    svg_group( array( 'fill' => $cl['text']['rgb'], 'fill-opacity' => $cl['text']['opacity'], 'stroke-width' => '0', 'font-family' => SVG_FONT, 'font-size' => '10pt', 'text-anchor' => 'end' ) );
            for ($i=0; $i<=$y_ticks; $i++)
            {
                $label = ($i * $y_scale).$unit;
		$tx = $xlm - 16;
		$ty = (int)(($ih - $ybm) - ($i * $y_step) + 4);
		svg_text($tx, $ty, $label);
            }
	    svg_group_end();

	    svg_group( array( 'fill' => $cl['text']['rgb'], 'fill-opacity' => $cl['text']['opacity'], 'stroke-width' => '0', 'font-family' => SVG_FONT, 'font-size' => '10pt', 'text-anchor' => 'middle' ) );
            for ($i=0; $i<$x_ticks; $i++)
            {
                $label = $data[$i]['img_label'];
		svg_text($xlm + ($i * $x_step) + ($x_step / 2), $ih - $ybm + 22, $label);
            }
	    svg_group_end();
        }

        //
        // legend
        //
        $legend_x = $xlm;
        if ($show_rx === '1') {
            svg_rect($legend_x, $ih-$ybm+39, 10, 10, array( 'stroke' => 'none', 'fill' => $cl['rx']['rgb'], 'rx' => '2', 'ry' => '2') );
	    svg_text($legend_x+16, $ih-$ybm+48, ucfirst(T('bytes in')), array( 'fill' => $cl['text']['rgb'], 'stroke-width' => 0, 'font-family' => SVG_FONT, 'font-size' => '8pt') );
            $legend_x += 120;
        }

        if ($show_tx === '1') {
            svg_rect($legend_x , $ih-$ybm+39, 10, 10, array( 'stroke' => 'none', 'fill' => $cl['tx']['rgb'], 'rx' => '2', 'ry' => '2') );
	    svg_text($legend_x+16, $ih-$ybm+48, ucfirst(T('bytes out')), array( 'fill' => $cl['text']['rgb'], 'stroke-width' => 0, 'font-family' => SVG_FONT, 'font-size' => '8pt') );
            $legend_x += 120;
        }

        if ($show_total === '1') {
            svg_rect($legend_x , $ih-$ybm+39, 10, 10, array( 'stroke' => 'none', 'fill' => $cl['total']['rgb'], 'rx' => '2', 'ry' => '2') );
	    svg_text($legend_x+16, $ih-$ybm+48, T('Total'), array( 'fill' => $cl['text']['rgb'], 'stroke-width' => 0, 'font-family' => SVG_FONT, 'font-size' => '8pt') );
        }
    }

    function output_image()
    {
        global $page,$hour,$day,$month,$iface;

        if ($page == 'summary')
            return;

        init_image();

        if ($page == 'h')
        {
            draw_data($hour);
        }
        else if ($page == 'd')
        {
            draw_data($day);
        }
        else if ($page == 'm')
        {
            draw_data($month);
        }

	svg_end();
    }

    get_vnstat_data();
    output_image();
?>
