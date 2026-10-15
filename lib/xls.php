<?php
/**
 * Excel 导出（Excel 2003 XML / SpreadsheetML，扩展名 .xls）。
 * 为什么用这个格式：不需要任何 PHP 扩展（不用 zip、不用 mbstring），
 * 输出的是纯文本，Excel / WPS / Numbers 都能直接打开，并且支持颜色、边框、
 * 自动换行、列宽、横向打印——正是「一周一张表」需要的样子。
 */

require_once __DIR__ . '/bootstrap.php';

/* ------------------------- 基础工具 ------------------------- */

/** 转成合法 XML 文本 */
function rq_xml_text($s)
{
    $s = str_replace(["\r\n", "\r"], "\n", (string) $s);

    // 丢掉非法 UTF-8 字节（否则 Excel 打开会乱码或报错）
    if (function_exists('mb_convert_encoding')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    } elseif (function_exists('iconv')) {
        $s = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
    }

    // 去掉 XML 1.0 不允许的控制字符（按字节处理，不会破坏多字节汉字）
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);

    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** 科目 -> 单元格样式 id（配色固定跟着科目走，不跟排列顺序变） */
function rq_xls_subject_style($subject)
{
    $order = array_keys(SUBJECTS);
    $idx   = array_search($subject, $order, true);
    if ($idx === false) {
        $idx = 0;
    }
    return 'sSubj' . $idx;
}

/* ------------------------- 样式表 ------------------------- */

function rq_xls_styles()
{
    $border = '<Borders>'
        . '<Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B9C2CE"/>'
        . '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B9C2CE"/>'
        . '<Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B9C2CE"/>'
        . '<Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B9C2CE"/>'
        . '</Borders>';

    $subjectFills = ['#FDECE8', '#E8F0FE', '#F1EBFD', '#E6F7F0', '#FEF3E2', '#E4F5FA'];
    $subjectInks  = ['#B23A20', '#1D4ED8', '#6D28D9', '#047857', '#B45309', '#0E7490'];

    $xml = '<Styles>'
        . '<Style ss:ID="Default" ss:Name="Normal">'
        . '<Alignment ss:Vertical="Top" ss:WrapText="1"/>'
        . '<Font ss:FontName="微软雅黑" x:CharSet="134" ss:Size="11" ss:Color="#1F2937"/>'
        . '</Style>'
        // 大标题
        . '<Style ss:ID="sTitle">'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '<Font ss:FontName="微软雅黑" x:CharSet="134" ss:Size="16" ss:Bold="1" ss:Color="#111827"/>'
        . '</Style>'
        // 副标题/说明
        . '<Style ss:ID="sMeta">'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '<Font ss:FontName="微软雅黑" x:CharSet="134" ss:Size="9" ss:Color="#6B7280"/>'
        . '</Style>'
        // 表头
        . '<Style ss:ID="sHead">'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>'
        . '<Font ss:FontName="微软雅黑" x:CharSet="134" ss:Size="11" ss:Bold="1" ss:Color="#334155"/>'
        . '<Interior ss:Color="#EEF2F7" ss:Pattern="Solid"/>'
        . $border
        . '</Style>'
        // 内容单元格
        . '<Style ss:ID="sCell">'
        . '<Alignment ss:Horizontal="Left" ss:Vertical="Top" ss:WrapText="1"/>'
        . '<Font ss:FontName="微软雅黑" x:CharSet="134" ss:Size="11" ss:Color="#1F2937"/>'
        . $border
        . '</Style>'
        // 空白单元格
        . '<Style ss:ID="sBlank">'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '<Font ss:FontName="微软雅黑" x:CharSet="134" ss:Size="11" ss:Color="#C7CDD6"/>'
        . '<Interior ss:Color="#FAFBFC" ss:Pattern="Solid"/>'
        . $border
        . '</Style>'
        // 科目列
        . '<Style ss:ID="sSubjLabel">'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '<Font ss:FontName="微软雅黑" x:CharSet="134" ss:Size="13" ss:Bold="1" ss:Color="#334155"/>'
        . '<Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/>'
        . $border
        . '</Style>'
        // 明细表
        . '<Style ss:ID="sDHead">'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
        . '<Font ss:FontName="微软雅黑" x:CharSet="134" ss:Size="11" ss:Bold="1" ss:Color="#334155"/>'
        . '<Interior ss:Color="#EEF2F7" ss:Pattern="Solid"/>'
        . $border
        . '</Style>'
        . '<Style ss:ID="sDCenter">'
        . '<Alignment ss:Horizontal="Center" ss:Vertical="Top" ss:WrapText="1"/>'
        . '<Font ss:FontName="微软雅黑" x:CharSet="134" ss:Size="11" ss:Color="#1F2937"/>'
        . $border
        . '</Style>';

    // 六个科目的彩色单元格
    foreach ($subjectFills as $i => $fill) {
        $xml .= '<Style ss:ID="sSubj' . $i . '">'
            . '<Alignment ss:Horizontal="Center" ss:Vertical="Center"/>'
            . '<Font ss:FontName="微软雅黑" x:CharSet="134" ss:Size="13" ss:Bold="1" ss:Color="' . $subjectInks[$i] . '"/>'
            . '<Interior ss:Color="' . $fill . '" ss:Pattern="Solid"/>'
            . $border
            . '</Style>';
    }

    return $xml . '</Styles>';
}

/* ------------------------- 工作表 ------------------------- */

/**
 * 单元格：['v' => 值, 's' => 样式id, 'm' => 横向合并几列, 't' => 'String'|'Number']
 * 行：['h' => 行高, 'cells' => [单元格...]]
 */
function rq_xls_sheet(array $sheet)
{
    $name = isset($sheet['name']) ? $sheet['name'] : 'Sheet1';
    $xml  = '<Worksheet ss:Name="' . rq_xml_text($name) . '"><Table>';

    if (!empty($sheet['cols'])) {
        foreach ($sheet['cols'] as $w) {
            $xml .= '<Column ss:AutoFitWidth="0" ss:Width="' . (float) $w . '"/>';
        }
    }

    foreach ($sheet['rows'] as $row) {
        $xml .= '<Row';
        if (!empty($row['h'])) {
            $xml .= ' ss:Height="' . (float) $row['h'] . '"';
        }
        $xml .= '>';
        foreach ($row['cells'] as $cell) {
            $style = isset($cell['s']) ? ' ss:StyleID="' . rq_xml_text($cell['s']) . '"' : '';
            $merge = (!empty($cell['m']) && $cell['m'] > 0) ? ' ss:MergeAcross="' . (int) $cell['m'] . '"' : '';
            $type  = isset($cell['t']) ? $cell['t'] : 'String';
            $value = isset($cell['v']) ? $cell['v'] : '';
            $xml .= '<Cell' . $style . $merge . '><Data ss:Type="' . $type . '">'
                . rq_xml_text($value) . '</Data></Cell>';
        }
        $xml .= '</Row>';
    }

    $xml .= '</Table>';
    // 横向、适合打印（只放最必要的两个节点，保证兼容性）
    $xml .= '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">'
        . '<PageSetup><Layout x:Orientation="Landscape"/>'
        . '<PageMargins x:Bottom="0.5" x:Left="0.4" x:Right="0.4" x:Top="0.5"/></PageSetup>'
        . '<ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios>'
        . '</WorksheetOptions>';
    $xml .= '</Worksheet>';

    return $xml;
}

/** 拼出整个工作簿 */
function rq_xls_build(array $sheets)
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<?mso-application progid="Excel.Sheet"?>' . "\n"
        . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:o="urn:schemas-microsoft-com:office:office"'
        . ' xmlns:x="urn:schemas-microsoft-com:office:excel"'
        . ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"'
        . ' xmlns:html="http://www.w3.org/TR/REC-html40">';

    $xml .= rq_xls_styles();

    foreach ($sheets as $sheet) {
        $xml .= rq_xls_sheet($sheet);
    }

    return $xml . '</Workbook>';
}

/** 输出下载 */
function rq_xls_send($filenameBase, array $sheets)
{
    $xml = rq_xls_build($sheets);

    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filenameBase);
    $ascii = str_replace(['"', '\\', '/'], '_', (string) $ascii);
    if (trim($ascii, '_ ') === '') {
        $ascii = 'riqingbiao';
    }

    if (!headers_sent()) {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $ascii . '.xls"; filename*=UTF-8\'\'' . rawurlencode($filenameBase . '.xls'));
        header('Cache-Control: no-store');
        header('Content-Length: ' . strlen($xml));
    }
    echo $xml;
    exit;
}

/* ------------------------- 具体两张表 ------------------------- */

/**
 * 周表：行 = 科目，列 = 星期，格子里是当天内容
 */
function rq_xls_week_sheet($start, $end, array $records, $title = '', $note = '')
{
    $days = rq_date_range($start, $end, 62);

    $cols = [64];
    foreach ($days as $d) {
        $cols[] = count($days) > 10 ? 110 : 170;
    }

    $rows = [];

    // 标题行
    $titleText = ($title !== '' ? $title . '　' : '') . rq_date_cn($start);
    if ($start !== $end) {
        $titleText .= ' ~ ' . rq_date_cn($end);
    }
    $rows[] = ['h' => 36, 'cells' => [['v' => $titleText, 's' => 'sTitle', 'm' => count($days)]]];

    // 说明行
    $meta = [];
    if (rq_site_sub() !== '') {
        $meta[] = rq_site_sub();
    }
    $meta[] = '导出时间 ' . date('Y-m-d H:i');
    $meta[] = '空白 = 当天未填写';
    if ($note !== '') {
        $meta[] = $note;
    }
    $rows[] = ['h' => 20, 'cells' => [['v' => implode('　|　', $meta), 's' => 'sMeta', 'm' => count($days)]]];

    // 表头
    $head = [['v' => '科目', 's' => 'sHead']];
    foreach ($days as $d) {
        $head[] = ['v' => rq_weekday_short($d) . ' ' . rq_date_md($d), 's' => 'sHead'];
    }
    $rows[] = ['h' => 30, 'cells' => $head];

    // 各科（顺序可在后台调整）
    foreach (rq_subjects() as $key => $label) {
        $cells = [['v' => $label, 's' => rq_xls_subject_style($key)]];
        foreach ($days as $d) {
            $content = isset($records[$d][$key]['content']) ? trim((string) $records[$d][$key]['content']) : '';
            if ($content === '') {
                $cells[] = ['v' => '—', 's' => 'sBlank'];
            } else {
                $cells[] = ['v' => $content, 's' => 'sCell'];
            }
        }
        $rows[] = ['h' => 110, 'cells' => $cells];
    }

    return ['name' => '周表', 'cols' => $cols, 'rows' => $rows];
}

/**
 * 明细表：一条记录一行
 */
function rq_xls_detail_sheet($start, $end, array $records)
{
    $rows = [];
    $rows[] = ['h' => 30, 'cells' => [
        ['v' => '日期', 's' => 'sDHead'],
        ['v' => '星期', 's' => 'sDHead'],
        ['v' => '科目', 's' => 'sDHead'],
        ['v' => '内容', 's' => 'sDHead'],
        ['v' => '最后更新', 's' => 'sDHead'],
    ]];

    foreach (rq_date_range($start, $end, 400) as $d) {
        foreach (rq_subjects() as $key => $label) {
            $content = isset($records[$d][$key]['content']) ? trim((string) $records[$d][$key]['content']) : '';
            if ($content === '') {
                continue;
            }
            $updated = isset($records[$d][$key]['updated_at']) ? (string) $records[$d][$key]['updated_at'] : '';
            $rows[] = ['h' => 34, 'cells' => [
                ['v' => $d, 's' => 'sDCenter'],
                ['v' => rq_weekday_cn($d), 's' => 'sDCenter'],
                ['v' => $label, 's' => rq_xls_subject_style($key)],
                ['v' => $content, 's' => 'sCell'],
                ['v' => $updated, 's' => 'sDCenter'],
            ]];
        }
    }

    return ['name' => '明细', 'cols' => [80, 60, 60, 420, 110], 'rows' => $rows];
}
