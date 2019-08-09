<?php
namespace Gtbank\Orders\Api;
interface OrderInterface
{
	/**
     * Returns greeting message to user
     * @api
     * @return message.
     */
    public function getOrders();
	
	/**
     * Returns greeting message to user
     * @api
	 * @param string $itemId itemId.
     * @return customarray
     */
	public function getItemDetails($itemId);
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $cartId cartId.
	 * @param string $message.
     * @return customarray
     */
    public function deliveryCommnent($cartId,$message);
	
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $itemId itemId.
	 * @param string $reason.
     * @return customarray
     */
    public function cancelOrder($itemId,$reason);

    /**
     * Returns greeting message to user
     * @api
     * @param string[] $orderIds orderIds
     * @return customarray
     */
    public function cancelOrders($orderIds);
	

	/**
     * Returns greeting message to user
     * @api
     * @param string $itemId itemId.
     * @return customarray
     **/
	public function printInvice($itemId);
	

	/**
     * Returns greeting message to user
     * @api
     * @param string $itemId itemId.
     * @return customarray
     **/
	public function cancelOrderDetail($itemId);
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $itemId itemId.
     * @return customarray
     **/
	public function getRepeatItemOrder($itemId);
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $cartId cartId.
     * @return customarray
     */
    public function getDeliveryCommnent($cartId);
	
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $cartId cartId.
     * @return customarray
     **/
	public function getOrderConfirmation($cartId);
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $trakingId trakingId.
     * @return customarray
     **/
	public function OrderTrakingDetails($trakingId);
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $quoteId quoteId.
	 * @param string $itemId itemId.
	 * @param string $discount discount.
	 * @param string $couponcode couponcode.
	 * @param string $type type
	 * @param string $redemptionType type
	 * @param float $maxRedemption maxRedemption
	 * @param int $limit limit	
     * @return customarray
     **/
	public function applyDiscount($quoteId,$itemId,$discount,
	$couponcode,$type,$redemptionType="",$maxRedemption=0,$limit=0);

	/**
     * Returns greeting message to user
     * @api
     * @param string $quoteId quoteId.
     * @return customarray
     **/
	public function marchantTotal($quoteId);
		
	/**
     * Returns greeting message to user
     * @api
     * @param string $orderIds orderIds.
     * @return customarray
     **/
	public function orderDetailsById($orderIds);
	
}