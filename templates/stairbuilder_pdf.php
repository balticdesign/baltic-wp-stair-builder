<!-- pdf_template.php -->
<style>
    @page {
        margin: 20px;
        font-family: Arial, Helvetica, sans-serif;
        width: 100%;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        font-family: Arial, Helvetica, sans-serif;
    }

    h1 {
        font-size: 1em;
    }

    h3 {
        font-size: 1em;
        margin: 0px;
        padding: 0px;
    }

    .wrapper {
        overflow: hidden;
    }

    .leftcol {
        width: 59%;
        float: left;
        font-family: Arial, Helvetica, sans-serif;
    }

    .rightcol {
        width: 39%;
        float: right;
        font-family: Arial, Helvetica, sans-serif;
    }

    .col {
        width: 33%;
        float: left;
    }

    .panel {
        font-size: 1.2em;
        vertical-align: top;
        font-family: Arial, Helvetica, sans-serif;
    }

    .panel table {
        font-family: Arial, Helvetica, sans-serif;
        padding: 10px;
        width: 100%;
    }

    .table-wrapper {
        height: 220px;
        margin-right: 10px;
    }

    .lbl {
        font-weight: bold;
        width: 60%;
    }

    .vl {
        padding-left: 10px;
        text-transform: capitalize;
    }

    .vl.lc {
        text-transform: lowercase;
    }

    .clear {
        clear: both;
        width: 100%;
        display: block;
    }
</style>
<table style="margin-bottom:20px;">
    <tr>
        <td><img src="https://stairsdirectlondon.com/wp-content/uploads/2023/07/SDL_logo.png" alt="SDL Logo" width="280px"></td>
        <td style="text-align: right;">
            <h1><?php echo $title; ?></h1>
        </td>
    </tr>
</table>
<div class="wrapper">
    <div class="leftcol">
        <div class="panel">
            <h3>Staircase Plan</h3>
            <div style="background-color:#e1e6f7; text-align:left;">
                <img src="<?php echo $content['canvas_image_path']; ?>" alt="StairCase Diagram" width="100%">
            </div>
        </div>
    </div>
    <div class="rightcol">
        <div class="panel">
            <h3>Staircase Essentials</h3>
            <table style="background-color:#e1e6f7;">
                <?php if ($content['sc-direction']) { ?>
                    <tr>
                        <td class="lbl">Direction: </td>
                        <td class="vl"><?php echo $content['sc-direction']; ?></td>
                    </tr>
                <?php } ?>
                <tr>
                    <td class="lbl">Floor to Floor: </td>
                    <td class="vl lc"><?php echo $content['floor-height']; ?>mm</td>
                </tr>
                <tr>
                    <td class="lbl">Staircase Width: </td>
                    <td class="vl lc"><?php echo $content['stair-width']; ?>mm</td>
                </tr>
                <tr>
                    <td class="lbl">Risers: </td>
                    <td class="vl lc"><?php echo $content['risers']; ?></td>
                </tr>
                <tr>
                    <td class="lbl">Going: </td>
                    <td class="vl lc"><?php echo $content['going']; ?>mm</td>
                </tr>
            </table>
        </div>
        <div class="panel">
            <h3 style="margin-top:20px;">Order Totals</h3>
            <table style="background-color:#e1e6f7; height:100%">
                <tr>
                    <th style="text-align:left">Name</th>
                    <th style="text-align:left">Qty</th>
                    <th style="text-align:left">Price</th>
                </tr>
                <?php foreach ($content['order_items'] as $item) { ?>
                    <tr>
                        <td><?php echo $item['name'] . '</td><td style="text-align:center;">' . $item['quantity'] . '</td><td>£' . $item['subtotal']; ?></td>
                    </tr>
                <?php } ?>
                <tr>
                    <td>VAT:</td>
                    <td></td>
                    <td>£<?php echo $content['total_tax']; ?></td>
                </tr>
                <tr>
                    <td colspan="3" style="border-top: 1px solid #154782;"></td>
                </tr>
                <tr>
                    <td>Total:</td>
                    <td></td>
                    <td><strong>£<?php echo $content['order_total']; ?></strong></td>
                </tr>
            </table>
        </div>
    </div>
</div>
<div class="wrapper">
    <div class="col">
        <div class="panel" style="display:block;">
            <h3 style="margin-top:20px;">Staircase Details</h3>
            <div class="table-wrapper" style="background-color:#e1e6f7;">
                <table>
                    <tr>
                        <td class="lbl">Construction Type:</td>
                        <td class="vl"> <?php echo $content['construction_type']; ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Tread Profile:</td>
                        <td class="vl"> <?php echo $content['tread-profile']; ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">String Material:</td>
                        <td class="vl"> <?php echo $content['stringer_material']; ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Tread Material:</td>
                        <td class="vl"> <?php echo $content['tread_material']; ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Riser Material:</td>
                        <td class="vl"> <?php echo $content['riser_material']; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="panel">
            <h3 style="margin-top:20px;">Newel Posts</h3>
            <div class="table-wrapper" style="background-color:#e1e6f7;">
                <table>
                    <tr>
                        <td class="lbl">Type:</td>
                        <td class="vl"> <?php echo $content['newel_type']; ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Material:</td>
                        <td class="vl"> <?php echo $content['newel_material']; ?></td>
                    </tr>
                    <tr>
                        <?php
                        if ($content['box-post']) {
                            $add1 = 1;
                        } else {
                            $add1 = 0;
                        }
                        $newel_number = $add1 + intval($content['tl-post']) + intval($content['tr-post']) + intval($content['to-post']) + intval($content['bo-post']) + intval($content['box-post']) + intval($content['bl-post']) + intval($content['br-post']);
                        ?>
                        <td class="lbl">Number:</td>
                        <td class="vl"> <?php echo  $newel_number; ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Caps:</td>
                        <td class="vl"> <?php echo $content['newel_cap']; ?></td>
                    </tr>
                    <?php if (($content['newel_cap']) != 'none') { ?>
                        <tr>
                            <td class="lbl">Cap Number:</td>
                            <td class="vl"> <?php echo $newel_number; ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="panel">
            <h3 style="margin-top:20px;">Ballustrading</h3>
            <div class="table-wrapper" style="background-color:#e1e6f7; margin-right: 0px;">
                <table>
                    <tr>
                        <td class="lbl">Handrail Type:</td>
                        <td class="vl"> <?php echo $content['handrail_type']; ?></td>
                    <tr>
                        <td class="lbl">Handrail Material:</td>
                        <td class="vl"> <?php echo $content['hdr_material']; ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Baserail Material:</td>
                        <td class="vl"> <?php echo $content['bsr_material']; ?></td>
                    </tr>
                    </tr>
                    <tr>
                        <td class="lbl">Spindles:</td>
                        <td class="vl"> <?php echo $content['spindle_type']; ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Spindle Number:</td>
                        <td class="vl"> <?php echo $content['spindle_type']; ?></td>
                    </tr>
                    <tr>
                        <td class="lbl">Spindle Material:</td>
                        <td class="vl"> <?php echo $content['bal_material']; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="wrapper" style="margin-top:20px;">
    <div class="leftcol">
        <div class="panel">
            <h3>Shipping Address</h3>
            <div style="background-color:#e1e6f7; padding:10px; font-size:16px;">
                <?php echo $content['shipping_address']; ?>
            </div>
        </div>
    </div>
    <div class="rightcol">
        <div class="panel">
            <h3>Notes</h3>

        </div>
    </div>
    
</div>
<div style="margin-top:10px; text-align:center; padding-top:15px; border-top:1px solid #154782; font-family:Arial, Helvetica, sans-serif;">
    Stairs Direct London Ltd. Unit 4, Chase Farm, Southgate Road, London, EN6 5ER | Tel: <a href="tel:07955511100">0795 551 1100</a> | Email: <a href="mailto:stairsdirectlondon@gmail.com">stairsdirectlondon@gmail.com</a></div>
<?php
// foreach ($content as $key => $value) {
//   echo "<p><strong>$key:</strong> $value</p>";
// } 
?>