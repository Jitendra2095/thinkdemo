<?php

namespace app\common\entity;

use think\Loader;
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Cell;
use PHPExcel_Writer_Excel5;
use PHPExcel_Writer_Excel2007;

class Export
{
    /**
     * 创建(导出)Excel数据表格
     * @param  array $list 要导出的数组格式的数据
     * @param  string $filename 导出的Excel表格数据表的文件名
     * @param  array $header Excel表格的表头
     * @param  array $index $list数组中与Excel表格表头$header中每个项目对应的字段的名字(key值)
     * 比如: $header = array('编号','姓名','性别','年龄');
     *       $index = array('id','username','sex','age');
     *       $list = array(array('id'=>1,'username'=>'YQJ','sex'=>'男','age'=>24));
     * @return [array] [数组]
     */
    public static function createtable($list, $filename, $header = array(), $index = array())
    {
        header("Content-type:application/octet-stream");
        header("Accept-Ranges:bytes");
        header("Content-type:application/vnd.ms-excel");
//        header("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition:attachment;filename=" . $filename . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");
//        header("Content-Disposition:filename=" . $filename . ".xlsx");
        $teble_header = implode("\t", $header);
        $strexport = $teble_header . "\r";
        foreach ($list as $row) {
            foreach ($index as $val) {
                $strexport .= $row[$val] . "\t";
            }
            $strexport .= "\r";

        }
        $strexport = iconv('UTF-8', "GB2312//IGNORE", $strexport);
        exit($strexport);
    }


    /**
     * 导出excel表格
     * @param $columName
     * @param $list
     * @param string $setTitle
     * @param string $fileName
     * @return string
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    function exportExcel($columName, $list, $setTitle = 'Sheet1', $fileName = 'demo')
    {
        if (empty($columName) || empty($list)) {
            return '列名或者内容不能为空';
        }

        if (count($list[0]) != count($columName)) {
            return '列名跟数据的列不一致';
        }
        Vendor('phpexcel18.PHPExcel');
        //实例化PHPExcel类
        $PHPExcel = new PHPExcel();
        //获得当前sheet对象
        $PHPSheet = $PHPExcel->getActiveSheet();
        //定义sheet名称
        $PHPSheet->setTitle($setTitle);

        //excel的列 这么多够用了吧？不够自个加 AA AB AC ……
        $letter = [
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
            'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'
        ];
        //把列名写入第1行 A1 B1 C1 ...
        for ($i = 0; $i < count($list[0]); $i++) {
            //$letter[$i]1 = A1 B1 C1  $letter[$i] = 列1 列2 列3
            $PHPSheet->setCellValue("$letter[$i]1", "$columName[$i]");
        }
        //内容第2行开始
        foreach ($list as $key => $val) {
            //array_values 把一维数组的键转为0 1 2 3 ..
            foreach (array_values($val) as $key2 => $val2) {
                //$letter[$key2].($key+2) = A2 B2 C2 ……
                $PHPSheet->setCellValue($letter[$key2] . ($key + 2), $val2);
            }
        }
        //生成2007版本的xlsx
        ob_end_clean();

        ob_start();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        header('Content-Disposition: attachment;filename="' . $fileName . '.xlsx"');

        header('Cache-Control: max-age=0');

        $PHPWriter = \PHPExcel_IOFactory::createWriter($PHPExcel, 'Excel2007');

        $PHPWriter->save('php://output');
    }

    public function import()
    {
        Vendor('phpexcel18.PHPExcel');
        //实例化PHPExcel类
        $PHPExcel = new \PHPExcel();
        $file = request()->file('file');
        $info = $file->validate(['ext' => 'xlsx'])->move(ROOT_PATH . 'public/uploads');//上传验证后缀名,以及上传之后移动的地址
        if ($info) {
            $exclePath = $info->getSaveName();  //获取文件名
            $file_name = ROOT_PATH . 'public/uploads' . DS . $exclePath;//上传文件的地址
            $objReader = \PHPExcel_IOFactory::createReader("Excel2007");
            $obj_PHPExcel = $objReader->load($file_name, $encode = 'utf-8');  //加载文件内容,编码utf-8
            $excel_array = $obj_PHPExcel->getSheet(0)->toArray();   //转换为数组格式
            array_shift($excel_array);  //删除第一个数组(标题);
            $data = $excel_array;
            return $data;
        }
    }
}