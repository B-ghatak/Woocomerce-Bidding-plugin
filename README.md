# WooCommerce Car Bidding Plugin

This plugin adds bidding functionality to WooCommerce products, specifically designed for car auctions. Here are the key features:

## User-Facing Functionalities:

- Place bids on car products
- View current highest bid for each car
- See the ask price (regular price) for each car
- Minimum bid amount set to 90% of the ask price
- Real-time validation of bid amounts
- Responsive bidding interface compatible with Elementor
- Login requirement for placing bids
- View personal bid history in My Account area

## Admin-Facing Functionalities:

- Set regular price (ask price) for car products
- View all bids placed on each car product
- Close bidding on a specific car
- Restart bidding on a closed auction
- View winning bids for closed auctions

## General Features:

- Custom database table for storing bids
- Integration with WooCommerce product system
- Elementor widget for displaying bidding interface
- AJAX-powered bidding process for smooth user experience
- Automatic price updates based on highest bid
- Secure nonce verification for all bidding actions
- Custom 'My Bids' tab in WooCommerce My Account area

## Bid Management:

- Users can delete their own bids
- Admins can manage all bids
- Bid history shows bid status (highest bid, outbid, etc.)

## Display Features:

- Custom styling for bid information display
- SweetAlert2 integration for improved user notifications
- Responsive design for mobile and desktop use

## Security Features:

- User authentication for bid placement
- Nonce verification for all AJAX requests
- Input sanitization and validation

## Additional Features:

- Currency symbol support based on WooCommerce settings
- Customizable minimum bid percentage
- Error handling and user feedback for failed bids
- Automatic page reload after successful bid placement

This plugin enhances WooCommerce with a robust car bidding system, providing a seamless experience for both users and administrators in online car auctions.
