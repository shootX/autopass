import React, { useState } from 'react'
import clsx from 'clsx'
import {
  managerCallIconUrl,
  managerRefreshIconUrl,
  managerConfirmedIconUrl,
  managerVectorIconUrl,
  managerCancelIconUrl,
} from '@/assets/staticUrls'

type Status = 'Confirm' | 'Rescheduled' | 'Expired' | 'Deleted' | 'New'

interface ManagerOrderProps {
  status?: Status
  localizedStatus: string
  date: string
  type: string
  customer: { name: string; phone: string }
  onDelete: () => void
  onReschedule?: () => void
  onConfirmed: () => void
}

const statusColorMap: Record<
  Status,
  {
    capBg: string;
    capText: string;
    labelBg: string;
    labelText: string;
  }
> = {
  Confirm: {
    capBg: "#B0EFBC",
    capText: "#183D69",
    labelBg: "#17BA68",
    labelText: "#FFFFFF",
  },
  Rescheduled: {
    capBg: "#879AB1",
    capText: "#183D69",
    labelBg: "#183D69",
    labelText: "#F7B233",
  },
  Expired: {
    capBg: "#FFC6C6",
    capText: "#183D69",
    labelBg: "#BA1717",
    labelText: "#F7B233",
  },
  Deleted: {
    capBg: "#879AB1",
    capText: "#FFFFFF",
    labelBg: "#C8D1DC",
    labelText: "#879AB1",
  },
  New: {
    capBg: "#183D69",
    capText: "#F7B233",
    labelBg: "#F8BE54",
    labelText: "#183D69",
  },
};


function getStatusStyle(status: Status): React.CSSProperties {
  const { labelBg, labelText } = statusColorMap[status]

  const correctedBg = status === 'Deleted' ? '#C8D1DC' : labelBg

  return {
    backgroundColor: correctedBg,
    color: labelText,
    borderRadius: '8px',
    padding: '6px 12px',
    fontWeight: 600,
    fontSize: '1rem',
  }
}

export default function ManagerOrder({
  status = 'New',
  localizedStatus,
  date,
  customer,
  type,
  onDelete,
  onReschedule,
  onConfirmed
}: ManagerOrderProps) {
  const isConfirmed = status === 'Confirm'
  const isNew = status === 'New'
  const isDeleted = status === 'Deleted'
  const capStyle = {
    backgroundColor: statusColorMap[status].capBg,
    color: statusColorMap[status].capText,
  }

  const renderControlButtons = () => {
    return (
      <div className='order-controll-right-box'>
        <a href={`tel:+${customer.phone}`}>
          <button className='order-btn'>
            <img src={managerCallIconUrl} alt='cutomerCall' />
          </button>
        </a>
        {!isConfirmed && onReschedule && (
          <button
            className='order-btn'
            onClick={onReschedule}
            disabled={isDeleted}
          >
            <img src={managerRefreshIconUrl} alt='refresh' />
          </button>
        )}
        <button className='order-btn' disabled={isDeleted} onClick={onConfirmed}>
          <img src={managerConfirmedIconUrl} alt='confirmed' />

        </button>
      </div>
    )
  }

  return (
    <div
      className={clsx("manager-order-container", { "gray-scale": isDeleted })}
    >
      <div className='order-cap' style={capStyle}>
        <h4 style={{ color: statusColorMap[status].capText }}>{date}</h4>
        <span className='order-status' style={getStatusStyle(status)}>
          {localizedStatus}
        </span>
      </div>

      <div className='order-info'>
        <div className='washing-type-container'>
          <img
            src={managerVectorIconUrl}
            alt='washing-type-icon'
          />
          <p className='washing-type'>{type}</p>
        </div>

        <div className='customer-order-info'>
          <p className='customer-order-name'>{customer.name}</p>
          <p className='customer-order-phone-number'>{customer.phone}</p>
        </div>

        <div className='order-controll-panel'>
          <div>
            <button
              className='order-btn cancel'
              onClick={onDelete}
              disabled={isDeleted}
            >
              <img
                src={managerCancelIconUrl}
                alt='cancel-btn'
              />
            </button>
          </div>

          {(isNew || isDeleted) && renderControlButtons()}

          {status !== "New" && !isDeleted && (
            <div className='order-controll-right-box'>
              <a href={`tel:+${customer.phone}`}>
                <button className='order-btn'>
                  <img
                    src={managerCallIconUrl}
                    alt='cutomerCall'
                  />
                </button>
              </a>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
