# Tibber API Bundle

Integration of the Tibber energy provider GraphQL API into Symfony applications for querying prices, homes, and consumption data.

## Language

### Accounts & Identity

**Account**:
A configured connection to Tibber identified by an API access token.
_Avoid_: User, profile, connection

### Topology

**Home**:
A physical building, household, or metering location registered within a Tibber account.
_Avoid_: Property, household, house, premise

**Meter**:
The physical or virtual electricity metering device installed at a Home.
_Avoid_: Counter, clock

### Market & Pricing

**Price Info**:
Electricity market pricing encompassing current, daily, and forecasted hourly energy prices.
_Avoid_: Tariff, cost structure, rates

**Price Level**:
The classification of an energy price relative to historical rolling averages (such as VERY_CHEAP, CHEAP, NORMAL, EXPENSIVE, VERY_EXPENSIVE).
_Avoid_: Price tier, category, rating

**Energy Price**:
The exact monetary price per kilowatt-hour for a discrete time interval.
_Avoid_: Rate, cost

### Consumption

**Consumption**:
The volume of electrical energy consumed at a Home over a defined period (hourly, daily, monthly).
_Avoid_: Usage, burn, load
