<html>
<body bgcolor="black" color="white">
<?php
$file = $_REQUEST['file'];
$lord = false;
if ($_REQUEST['lord'] == '1')
    $lord = true;

$ansi_colours = array( '0' => array (
                           '30' => 'black',
                           '31' => 'maroon',
                           '32' => 'green',
                           '33' => 'olive',
                           '34' => 'navy',
                           '35' => 'purple',
                           '36' => 'teal',
                           '37' => 'gray', ),
                       '1' => array (                          
                           '30' => 'silver',
                           '31' => 'red',
                           '32' => 'lime',
                           '33' => 'yellow',
                           '34' => 'blue',
                           '35' => 'fuchsia',
                           '36' => 'aqua',
                           '37' => 'white' ) );

$lord_colours = array( '1' => 'navy',
                       '2' => 'green',
                       '3' => 'teal',
                       '4' => 'maroon',
                       '5' => 'purple',
                       '6' => 'olive',
                       '7' => 'gray',
                       '8' => 'gray',
                       '9' => 'blue',
                       '0' => 'lime',
                       '!' => 'aqua',
                       '@' => 'red',
                       '#' => 'fuchsia',
                       '$' => 'yellow',
                       '%' => 'white' );

class cell {
    var $style;
    var $data;

    function __construct()
    {
        $this->data = "&nbsp;";
    }

    function set_style($s)
    {
        $this->style = $s;
    }

    function set_data($d)
    {
        if ($d == " ")
            $this->data = "&nbsp;";
        else
            $this->data = $d;
    }

    function print_cell()
    {
        echo $this->style;
        echo $this->data;
    }
}

class row {
    var $cells = array();

    function get_cell($c)
    {
        if (!array_key_exists($c, $this->cells))
            $this->cells[$c] = new cell();
        return $this->cells[$c];
    }

    function set_style($col, $style)
    {
        $this->get_cell($col)->set_style($style);
    }

    function set_data($col, $data)
    {
        $this->get_cell($col)->set_data($data);
    }

    function print_row()
    {
        $last = 0;
        foreach ($this->cells as $key => &$cell)
        {
            for ($i = $last + 1; $i < $key; $i++)
                echo "&nbsp;";
            $cell->print_cell();
            $last = $key;
        }
        echo "<br>";
    }
}

class ansiblock {
    var $rows = array();

    function get_row($r)
    {
        if (!array_key_exists($r, $this->rows))
            $this->rows[$r] = new row();
        return $this->rows[$r];
    }

    function set_style($row, $col, $style)
    {
        $this->get_row($row)->set_style($col, $style);
    }

    function set_data($row, $col, $data)
    {
        $this->get_row($row)->set_data($col, $data);
    }

    function clear()
    {
        $this->rows = array();
    }

    function print_block()
    {
        $last = 0;
        foreach ($this->rows as $key => &$row)
        {
            for ($i = $last + 1; $i < $key; $i++)
                echo "<br>";
            $row->print_row();
            $last = $key;
        }
        echo "<br>";
    }
}

function get_attrs($code)
{
    $attrs = array();
    $sc_pos = strpos($code, ';');
    if ($sc_pos)
    {
        $attrs = get_attrs(substr($code, $sc_pos + 1));
        $code = substr($code, 0, $sc_pos);
    }
    array_push($attrs, $code);
    return $attrs;
}

?>

<tt>
<?php

$block = new ansiblock();

$lines = file("/var/www/$file");

$ansi_code_list = '';
$ansi_code = '';
$log = '';
foreach ($lines as $line_num => $line)
{
    $ansi_mode = false;
    $lord_colour_mode = false;
    $col = 1;
    for($x = 0; $x < strlen($line); $x++)
    {
        $letter = substr($line, $x, 1);
        if ($lord_colour_mode)
        {
            $style = '</span><span style=color:' .
                $lord_colours[$letter] . '>';
            $block->set_style($line_num+1, $col, $style);
            $lord_colour_mode = false;
        }
        else if (!$ansi_mode)
        {
            if ($letter == chr(0x1b))
            {
                $ansi_mode = true;
                $ansi_code = '';
                $ansi_code_list .= '<br>ESC ';
            }
            else if ($lord && $letter == '`')
            {
                $lord_colour_mode = true;
            }
            else
            {
                if ($letter == chr(0xc4))
                    $data = '&ndash;';
                else
                    $data = htmlentities($letter);
                $block->set_data($line_num + 1, $col, $data);
                $col++;
            }
        }
        else
        {
            $ansi_code_list .= $letter;
            if ($letter == 'm')
            {
                if ($ansi_code == '0')
                    $block->set_style($line_num+1, $col,
                                      '</span><span style=color:white>');
                else
                {
                    $attrs = get_attrs($ansi_code);
                    $highlight = 0;
                    $colour = 30;
                    $background = 0;
                    $blinking = false;

                    foreach ($attrs as $a)
                    {
                        if ($a == 0 || $a == 1)
                            $highlight = $a;
                        elseif ($a == 5 || $a == 6)
                            $blinking = true;
                        elseif ($a >= 30 && $a <= 37)
                            $colour = $a;
                        elseif ($a >= 40 && $a <= 47)
                            $background = $a;
                    }

                    //$log .= "code: $ansi_code highlight: $highlight colour: $colour (".$ansi_colours[$highlight][$colour].") background: $background (".$ansi_colours[$highlight][$background].")<br>";

                    $style = '</span><span style=color:' .
                             $ansi_colours[$highlight][$colour];
                    if ($background)
                        $style .= ';background:' .
                            $ansi_colours[$highlight][$background - 10];
                    if ($blinking)
                        $style .= ';text-decoration:blink';
                    $style .= '>';
                    $block->set_style($line_num+1, $col, $style);

                }
                $ansi_mode = false;
            }
            else if ($letter == 'J')
            {
                if ($ansi_code == '2')
                    $block->clear();
                $ansi_mode = false;
            }
            else if ($letter != '[')
                $ansi_code .= $letter;
        }
    }

    //$new_line = ereg_replace(0xc4, "-", $new_line);
    //$new_line = htmlentities($new_line);
    //$new_line .= "<br>";
    //echo $new_line;
}

echo '<span>';
$block->print_block();
echo '</span>'
/*
echo '<span style=color:white>';
echo '<br>';
echo $log;
echo $ansi_code_list;
echo '</span>';
*/
?>
</tt>
</body>
</html>

