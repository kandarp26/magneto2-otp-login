<?php
namespace Gtbank\Orders\Api;
interface RmaInterface
{
	/**
     * Returns greeting message to user
     * @api
     * @param string $itemId itemId.
     * @return customarray
     **/
	public function returnOrderDetail($itemId);
	
	/**
     * Returns greeting message to user
     * @api
     * @param string $itemId itemId.
	 * @param string $reasonId reasonId.
	 * @param string $qty qty.
     * @return customarray
     **/
	public function returnOrder($itemId,$reasonId,$qty);
}