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

    function allocate_color($im, $colors)
    {
	return imagecolorallocatealpha($im, $colors[0], $colors[1], $colors[2], $colors[3]);
    }

    function init_image()
    {
        global $im, $xlm, $xrm, $ytm, $ybm, $iw, $ih,$graph, $cl, $iface, $colorscheme, $style;

        if ($graph == 'none')
            return;

        //
        // image object
        //
        $xlm = 70;
        $xrm = 20;
        $ytm = 35;
        $ybm = 60;
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

        $im = imagecreatetruecolor($iw,$ih);

        //
        // colors
        //
	$cs = $colorscheme;
	$cl['image_background'] = allocate_color($im, $cs['image_background']);
	$cl['background'] = allocate_color($im, $cs['graph_background']);
	$cl['background_2'] = allocate_color($im, $cs['graph_background_2']);
        $cl['grid_stipple_1'] = allocate_color($im, $cs['grid_stipple_1']);
        $cl['grid_stipple_2'] = allocate_color($im, $cs['grid_stipple_2']);
        $cl['text'] = allocate_color($im, $cs['text']);
        $cl['border'] = allocate_color($im, $cs['border']);
        $cl['rx'] = allocate_color($im, $cs['rx']);
        $cl['rx_border'] = allocate_color($im, $cs['rx_border']);
        $cl['tx'] = allocate_color($im, $cs['tx']);
        $cl['tx_border'] = allocate_color($im, $cs['tx_border']);
        $cl['total'] = imagecolorallocatealpha($im, 30, 136, 229, 0);
        $cl['total_border'] = imagecolorallocatealpha($im, 13, 71, 161, 0);

        imagefilledrectangle($im,0,0,$iw,$ih,$cl['image_background']);
	imagefilledrectangle($im,$xlm,$ytm,$iw-$xrm,$ih-$ybm, $cl['background']);

	$x_step = ($iw - $xlm - $xrm) / 12;
	$depth = ($x_step / 8) + 4;
	imagefilledpolygon($im, array($xlm, $ytm, $xlm, $ih - $ybm, $xlm - $depth, $ih - $ybm + $depth, $xlm - $depth, $ytm + $depth), 4, $cl['background_2']);
	imagefilledpolygon($im, array($xlm, $ih - $ybm, $xlm - $depth, $ih - $ybm + $depth, $iw - $xrm - $depth, $ih - $ybm  + $depth, $iw - $xrm, $ih - $ybm), 4, $cl['background_2']);

	// draw title
	$text = T('Traffic data for')." $iface";
 	$bbox = imagettfbbox(10, 0, GRAPH_FONT, $text);
	$textwidth = $bbox[2] - $bbox[0];
	imagettftext($im, 10, 0, ($iw-$textwidth)/2, ($ytm/2), $cl['text'], GRAPH_FONT, $text);

    }

    function draw_border()
    {
        global $im,$cl,$iw,$ih;

        imageline($im,     0,    0,$iw-1,    0, $cl['border']);
        imageline($im,     0,$ih-1,$iw-1,$ih-1, $cl['border']);
        imageline($im,     0,    0,    0,$ih-1, $cl['border']);
        imageline($im, $iw-1,    0,$iw-1,$ih-1, $cl['border']);
    }

    function draw_grid($x_ticks, $y_ticks)
    {
        global $im, $cl, $iw, $ih, $xlm, $xrm, $ytm, $ybm;
        $x_step = ($iw - $xlm - $xrm) / ($x_ticks ?: 1);
        $y_step = ($ih - $ytm - $ybm) / $y_ticks;

	$depth = 10;//($x_step / 8) + 4;

        $ls = array($cl['grid_stipple_1'],$cl['grid_stipple_2']);
        imagesetstyle($im, $ls);
        for ($i=$xlm;$i<=($iw-$xrm); $i += $x_step)
        {
            imageline($im, $i, $ytm, $i, $ih - $ybm, IMG_COLOR_STYLED);
	    imageline($im, $i, $ih - $ybm, $i - $depth, $ih - $ybm + $depth, IMG_COLOR_STYLED);
        }
        for ($i=$ytm;$i<=($ih-$ybm); $i += $y_step)
        {
            imageline($im, $xlm, $i, $iw - $xrm, $i, IMG_COLOR_STYLED);
	    imageline($im, $xlm, $i, $xlm - $depth, $i + $depth, IMG_COLOR_STYLED);
        }
        imageline($im, $xlm, $ytm, $xlm, $ih - $ybm, $cl['border']);
        imageline($im, $xlm, $ih - $ybm, $iw - $xrm, $ih - $ybm, $cl['border']);
    }

    function draw_data($data)
    {
        global $im,$cl,$iw,$ih,$xlm,$xrm,$ytm,$ybm;
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
	imagesetthickness($im, 1);
        $sf = ($prescale * $y_scale * $y_ticks) / $gr_h;

        if (count($data) == 0)
        {
            $text = T('no data available');
	    $bbox = imagettfbbox(10, 0, GRAPH_FONT, $text);
	    $textwidth = $bbox[2] - $bbox[0];
	    imagettftext($im, 10, 0, ($iw-$textwidth)/2, $ytm + 80, $cl['text'], GRAPH_FONT, $text);
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
		    $rx_points[] = array($x, (int)($ytm + ($ih - $ytm - $ybm) - (($data[$i]['rx'] - $offset) / $sf)));
		}
        	if ($show_tx === '1') {
		    $tx_points[] = array($x, (int)($ytm + ($ih - $ytm - $ybm) - (($data[$i]['tx'] - $offset) / $sf)));
		}
        	if ($show_total === '1') {
		    $total = $data[$i]['rx'] + $data[$i]['tx'];
		    $total_points[] = array($x, (int)($ytm + ($ih - $ytm - $ybm) - (($total - $offset) / $sf)));
		}
            }

	    imagesetthickness($im, 2);
	    for ($i = 1; $i < count($rx_points); $i++) {
		imageline($im, $rx_points[$i - 1][0], $rx_points[$i - 1][1], $rx_points[$i][0], $rx_points[$i][1], $cl['rx']);
	    }
	    for ($i = 1; $i < count($tx_points); $i++) {
		imageline($im, $tx_points[$i - 1][0], $tx_points[$i - 1][1], $tx_points[$i][0], $tx_points[$i][1], $cl['tx']);
	    }
	    for ($i = 1; $i < count($total_points); $i++) {
		imageline($im, $total_points[$i - 1][0], $total_points[$i - 1][1], $total_points[$i][0], $total_points[$i][1], $cl['total']);
	    }

	    imagesetthickness($im, 1);
	    for ($i = 0; $i < count($rx_points); $i++) {
		imagefilledellipse($im, $rx_points[$i][0], $rx_points[$i][1], 7, 7, $cl['image_background']);
		imageellipse($im, $rx_points[$i][0], $rx_points[$i][1], 7, 7, $cl['rx_border']);
	    }
	    for ($i = 0; $i < count($tx_points); $i++) {
		imagefilledellipse($im, $tx_points[$i][0], $tx_points[$i][1], 7, 7, $cl['image_background']);
		imageellipse($im, $tx_points[$i][0], $tx_points[$i][1], 7, 7, $cl['tx_border']);
	    }
	    for ($i = 0; $i < count($total_points); $i++) {
		imagefilledellipse($im, $total_points[$i][0], $total_points[$i][1], 7, 7, $cl['image_background']);
		imageellipse($im, $total_points[$i][0], $total_points[$i][1], 7, 7, $cl['total_border']);
	    }

            //
            // axis labels
            //
            for ($i=0; $i<=$y_ticks; $i++)
            {
                $label = ($i * $y_scale).$unit;
		$bbox = imagettfbbox(8, 0, GRAPH_FONT, $label);
		$textwidth = $bbox[2] - $bbox[0];
		imagettftext($im, 8, 0, $xlm - $textwidth - 16, ($ih - $ybm) - ($i * $y_step) + 8, $cl['text'], GRAPH_FONT, $label);
            }

            for ($i=0; $i<$x_ticks; $i++)
            {
                $label = $data[$i]['img_label'];
		$bbox = imagettfbbox(9, 0, GRAPH_FONT, $label);
		$textwidth = $bbox[2] - $bbox[0];
		imagettftext($im, 9, 0, $xlm + ($i * $x_step) + ($x_step / 2) - ($textwidth / 2), $ih - $ybm + 20, $cl['text'], GRAPH_FONT, $label);
            }
        }

        draw_border();


        //
        // legend
        //
        $legend_x = $xlm;
        if ($show_rx === '1') {
            imagefilledrectangle($im, $legend_x, $ih-$ybm+39, $legend_x+8,$ih-$ybm+47,$cl['rx']);
            imagerectangle($im, $legend_x, $ih-$ybm+39, $legend_x+8,$ih-$ybm+47,$cl['text']);
	    imagettftext($im, 8,0, $legend_x+14, $ih-$ybm+48,$cl['text'], GRAPH_FONT,ucfirst(T('bytes in')));
            $legend_x += 120;
        }

        if ($show_tx === '1') {
            imagefilledrectangle($im, $legend_x, $ih-$ybm+39, $legend_x+8,$ih-$ybm+47,$cl['tx']);
            imagerectangle($im, $legend_x, $ih-$ybm+39, $legend_x+8,$ih-$ybm+47,$cl['text']);
	    imagettftext($im, 8,0, $legend_x+14, $ih-$ybm+48,$cl['text'], GRAPH_FONT,ucfirst(T('bytes out')));
            $legend_x += 120;
        }

        if ($show_total === '1') {
            imagefilledrectangle($im, $legend_x, $ih-$ybm+39, $legend_x+8,$ih-$ybm+47,$cl['total']);
            imagerectangle($im, $legend_x, $ih-$ybm+39, $legend_x+8,$ih-$ybm+47,$cl['text']);
	    imagettftext($im, 8,0, $legend_x+14, $ih-$ybm+48,$cl['text'], GRAPH_FONT,T('Total'));
        }
    }

    function output_image()
    {
        global $page,$hour,$day,$month,$im,$iface;

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

        header('Content-type: image/png');
        imagepng($im);
    }

    get_vnstat_data();
    output_image();
?>
