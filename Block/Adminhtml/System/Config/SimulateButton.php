<?php
declare(strict_types=1);

namespace GardenLawn\Core\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class SimulateButton extends Field
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    public function __construct(
        Context $context,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $url = $this->getUrl('gardenlawn_core/price/calculate');
        $optionsHtml = $this->getProductOptions();

        $html = '<button type="button" id="gardenlawn_simulate_btn" class="action-default scalable" onclick="openSimulationModal()">
            <span>' . __('Open Simulator') . '</span>
        </button>';

        $html .= "
        <div id='simulation-modal-content' style='display:none;'>
            <div class='admin__field'>
                <label class='admin__field-label'><span>Select Product</span></label>
                <div class='admin__field-control'>
                    <select class='admin__control-select' id='sim_sku' style='width: 100%;'>
                        <option value=''>-- Select Product --</option>
                        {$optionsHtml}
                    </select>
                </div>
            </div>
            <div class='admin__field' style='margin-top: 10px;'>
                <button type='button' class='action-primary' onclick='runSimulation()'>Calculate</button>
            </div>
            <div id='sim_result' style='margin-top: 20px; border-top: 1px solid #ccc; padding-top: 10px;'></div>
        </div>

        <script>
            require(['jquery', 'Magento_Ui/js/modal/modal'], function($, modal) {
                var options = {
                    type: 'popup',
                    responsive: true,
                    innerScroll: true,
                    title: 'Price Calculation Simulator',
                    buttons: []
                };

                window.openSimulationModal = function() {
                    var popup = modal(options, $('#simulation-modal-content'));
                    $('#simulation-modal-content').modal('openModal');
                };

                window.runSimulation = function() {
                    var sku = $('#sim_sku').val();
                    if(!sku) { alert('Please select a product'); return; }

                    $('#sim_result').html('Loading...');

                    $.ajax({
                        url: '$url',
                        data: {sku: sku, form_key: window.FORM_KEY},
                        type: 'POST',
                        dataType: 'json',
                        success: function(res) {
                            if(res.error) {
                                $('#sim_result').html('<div style=\"color:red\">' + res.error + '</div>');
                            } else {
                                var html = '<table class=\"data-grid\">';
                                html += '<tr><th>Parameter</th><th>Value</th></tr>';
                                html += '<tr><td>Product</td><td>' + res.product_name + '</td></tr>';
                                html += '<tr><td>Dealer Price (EUR)</td><td>' + res.dealer_price_eur + '</td></tr>';
                                html += '<tr><td>Rate (EUR->PLN)</td><td>' + res.rate + '</td></tr>';
                                html += '<tr><td>Dealer Price (PLN)</td><td>' + res.dealer_price_pln + '</td></tr>';
                                html += '<tr><td>Factor</td><td>' + res.calculation.factor + '</td></tr>';
                                html += '<tr><td>Base Net</td><td>' + res.calculation.net_base.toFixed(2) + '</td></tr>';
                                html += '<tr><td>Tax Rate</td><td>' + res.calculation.tax_rate + '%</td></tr>';
                                html += '<tr><td>Base Gross</td><td>' + res.calculation.gross_base.toFixed(4) + '</td></tr>';
                                html += '<tr><td><strong>Rounded Gross</strong></td><td><strong>' + res.calculation.gross_rounded.toFixed(2) + '</strong></td></tr>';
                                html += '<tr><td><strong>Final Net</strong></td><td><strong>' + res.calculation.net_final.toFixed(4) + '</strong></td></tr>';
                                html += '</table>';
                                $('#sim_result').html(html);
                            }
                        },
                        error: function() {
                            $('#sim_result').html('<div style=\"color:red\">System Error</div>');
                        }
                    });
                };
            });
        </script>
        ";

        return $html;
    }

    private function getProductOptions(): string
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('dealer_price', 0, 'gt')
                ->setPageSize(200) // Limit to 200 products for performance
                ->create();

            $products = $this->productRepository->getList($searchCriteria)->getItems();

            $options = '';
            foreach ($products as $product) {
                $label = $product->getSku() . ' - ' . substr($product->getName(), 0, 50);
                $options .= "<option value='{$product->getSku()}'>{$label}</option>";
            }
            return $options;
        } catch (\Exception $e) {
            return "<option value=''>Error loading products</option>";
        }
    }
}
