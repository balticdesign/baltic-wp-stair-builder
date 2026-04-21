<!-- pdf_template.php -->
<style>
    @page {
        margin: 20px;
        font-family: Arial, Helvetica, sans-serif;
        width:90%;
    }
    span {

    }
</style>
<table cellspacing="10" style="width:90%;">
    <tr >
        <td style="width:60%;"><img src="https://stairsdirectlondon.com/wp-content/uploads/2023/07/SDL_logo.png" alt="SDL Logo" width="350px"></td>
        <td><h1><?php echo $title; ?></h1></td>
        </tr>
</table>
<div class="righcol">
    <tr style="margin-top:20px;">
    <td style="background-color:#e1e6f7; text-align:center;" ><img src="<?php echo $content['canvas_image_path']; ?>" alt="StairCase Diagram" width="80%"></td>
    <td style="background-color:#e1e6f7; padding:20px; font-size:1.4em; font-family: Arial, Helvetica, sans-serif; vertical-align:top;">
    <h3>Staircase Essentials</h3>
    <ul>
    <li><strong>Floor to Floor: </strong><?php echo $content['floor-height']; ?>mm</li>
    <li><strong>Staircase Width: </strong><?php echo $content['stair-width']; ?>mm</li>
    <li><strong>Risers: </strong><?php echo $content['risers']; ?></li>
    <li><strong>Going: </strong><?php echo $content['going']; ?>mm</li>
    </ul>
    <h3 style="margin-top:20px;">Order Totals</h3>
    <ul>
    <li><strong>Staircase Base Price:</strong> £<?php echo $content['final_price']; ?></li>
    <li><strong>Plus Stairparts:</strong> £<?php echo $content['total_without_vat']; ?></li>
    <li><strong>VAT:</strong> £<?php echo $content['total_tax']; ?></li>
    <li><strong>Total:</strong> £<?php echo $content['order_total']; ?></li>
    </ul>
    </td>
    </tr>
    <tr >
    <td style="background-color:#e1e6f7; padding:10px;">
<?php
foreach ($content as $key => $value) {
  echo "<p><strong>$key:</strong> $value</p>";
} ?></div></td></tr>
</table>